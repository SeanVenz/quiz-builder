<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handlers for Quiz Builder
 */

// Quiz submission handlers
add_action('wp_ajax_qb_save_quiz_answers', 'qb_save_quiz_answers');
add_action('wp_ajax_nopriv_qb_save_quiz_answers', 'qb_save_quiz_answers');
add_action('wp_ajax_qb_get_quiz_answers', 'qb_get_quiz_answers');
add_action('wp_ajax_nopriv_qb_get_quiz_answers', 'qb_get_quiz_answers');
add_action('wp_ajax_qb_clear_quiz_answers', 'qb_clear_quiz_answers');
add_action('wp_ajax_nopriv_qb_clear_quiz_answers', 'qb_clear_quiz_answers');
add_action('wp_ajax_qb_submit_quiz', 'qb_ajax_submit_quiz');
add_action('wp_ajax_nopriv_qb_submit_quiz', 'qb_ajax_submit_quiz');

// Admin AJAX handlers
add_action('wp_ajax_qb_get_attempt_details', 'qb_get_attempt_details');
add_action('wp_ajax_qb_export_attempts_csv', 'qb_export_attempts_csv');
add_action('wp_ajax_qb_get_quiz_settings', 'qb_get_quiz_settings');

// Onboarding AJAX handlers
add_action('wp_ajax_qb_complete_onboarding', 'qb_complete_onboarding');
add_action('wp_ajax_qb_onboarding_create_quiz', 'qb_onboarding_create_quiz');
add_action('wp_ajax_qb_onboarding_add_question', 'qb_onboarding_add_question');
add_action('wp_ajax_qb_onboarding_add_questions', 'qb_onboarding_add_questions');
add_action('wp_ajax_qb_onboarding_add_options', 'qb_onboarding_add_options');
add_action('wp_ajax_qb_onboarding_add_all_options', 'qb_onboarding_add_all_options');

/**
 * Save quiz answers temporarily
 */
function qb_save_quiz_answers() {
    check_ajax_referer('qb_save_answers', 'nonce');

    if (!isset($_POST['quiz_id']) || !isset($_POST['answers'])) {
        wp_send_json_error('Missing required data');
    }    $quiz_id = intval($_POST['quiz_id']);
    
    // Sanitize JSON input properly
    $answers_json = isset($_POST['answers']) ? sanitize_textarea_field(wp_unslash($_POST['answers'])) : '';
    $raw_answers = !empty($answers_json) ? json_decode($answers_json, true) : array();
    if (!is_array($raw_answers)) {
        $raw_answers = array();
    }
    
    // Sanitize answers array
    $answers = array();
    foreach ($raw_answers as $key => $value) {
        $answers[sanitize_text_field($key)] = sanitize_text_field($value);
    }

    // Generate a unique key for this user's quiz attempt
    $user_id = get_current_user_id();
    $key = 'quiz_answers_' . ($user_id ? $user_id : 'guest') . '_' . $quiz_id;

    // Store answers in transient (expires in 1 hour)
    set_transient($key, $answers, HOUR_IN_SECONDS);

    wp_send_json_success();
}

/**
 * Get attempt details for modal display
 */
function qb_get_attempt_details() {
    check_ajax_referer('qb_attempt_details', 'nonce');

    global $wpdb;
    $attempt_id = isset($_POST['attempt_id']) ? sanitize_text_field(wp_unslash($_POST['attempt_id'])) : '';
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $users_table = $wpdb->users;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
    $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $attempts_table WHERE random_id = %s", $attempt_id));
    if (!$attempt) {
        wp_send_json_error('Attempt not found');
    }

    // Get quiz and user details
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quizzes_table WHERE id = %d", $attempt->quiz_id));
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $user = $attempt->user_id ? $wpdb->get_row($wpdb->prepare("SELECT display_name FROM $users_table WHERE ID = %d", $attempt->user_id)) : null;

    $answers = json_decode($attempt->answers, true);

    // Use output buffering to capture template output
    ob_start();
    include QB_PATH . 'templates/attempt-details.php';
    $output = ob_get_clean();

    wp_send_json_success(array('html' => $output));
}

/**
 * Export attempts to CSV
 */
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
    
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $attempts = $wpdb->get_results("SELECT a.*, q.title as quiz_title, u.display_name as user_name FROM $attempts_table a LEFT JOIN $quizzes_table q ON a.quiz_id = q.id LEFT JOIN $users_table u ON a.user_id = u.ID ORDER BY a.created_at DESC");
    
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
        ));    }
    
    // Use WordPress filesystem method
    if (is_resource($output)) {
        fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Required for CSV stream output
    }
    exit;
}

/**
 * Get quiz settings via AJAX
 */
function qb_get_quiz_settings() {
    check_ajax_referer('qb_get_settings', 'nonce');

    if (!isset($_POST['quiz_id'])) {
        wp_send_json_error('Missing quiz ID');
    }
    
    $quiz_id = intval($_POST['quiz_id']);
    require_once QB_PATH . 'includes/db/class-quiz-settings-db.php';
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
 * Complete onboarding process
 */
function qb_complete_onboarding() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    // Mark onboarding as completed
    update_option('qb_onboarding_completed', true);
    
    wp_send_json_success(array(
        'redirect_url' => admin_url('admin.php?page=quiz-builder')
    ));
}

/**
 * Create quiz during onboarding
 */
function qb_onboarding_create_quiz() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $title = isset($_POST['quiz_title']) ? sanitize_text_field(wp_unslash($_POST['quiz_title'])) : '';
    $description = isset($_POST['quiz_description']) ? sanitize_textarea_field(wp_unslash($_POST['quiz_description'])) : '';

    if (empty($title)) {
        wp_send_json_error('Quiz title is required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_quizzes';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $result = $wpdb->insert($table_name, [ 'title' => $title,'description' => $description,'created_at' => current_time('mysql')]);

    if ($result === false) {
        wp_send_json_error('Failed to create quiz');
    }

    wp_send_json_success(['quiz_id' => $wpdb->insert_id]);
}

/**
 * Add question during onboarding
 */
function qb_onboarding_add_question() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    $question_text = isset($_POST['question_text']) ? sanitize_text_field(wp_unslash($_POST['question_text'])) : '';
    
    if (empty($question_text)) {
        wp_send_json_error('Question text is required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_questions';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $result = $wpdb->insert($table_name, [ 'quiz_id' => $quiz_id, 'question' => $question_text, 'order' => 1 ]);

    if ($result === false) {
        wp_send_json_error('Failed to create question');
    }

    wp_send_json_success(['question_id' => $wpdb->insert_id]);
}

/**
 * Add multiple questions during onboarding
 */
function qb_onboarding_add_questions() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    
    if (empty($quiz_id)) {
        wp_send_json_error('Quiz ID is required');
    }
    
    // Handle questions data - could be array or JSON string
    $questions = array();
    
    if (isset($_POST['questions'])) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Will be unslashed and sanitized below
        $raw_questions_data = $_POST['questions'];
        
        if (is_array($raw_questions_data)) {
            // Direct array - first unslash, then sanitize each element
            $unslashed_questions = wp_unslash($raw_questions_data);
            $questions = array_map('sanitize_text_field', $unslashed_questions);
        } else {
            // JSON string
            $questions_json = sanitize_textarea_field(wp_unslash($raw_questions_data));
            $decoded_questions = !empty($questions_json) ? json_decode($questions_json, true) : array();
            
            if (is_array($decoded_questions)) {
                $questions = array_map('sanitize_text_field', $decoded_questions);
            }
        }
    }
    
    // Filter out empty questions
    $questions = array_filter($questions, function($question) {
        return !empty(trim($question));
    });
    
    if (empty($questions)) {
        wp_send_json_error('At least one question is required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_questions';
    $question_ids = [];

    foreach ($questions as $index => $question_text) {
        if (empty($question_text)) {
            continue;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($table_name, [ 'quiz_id' => $quiz_id, 'question' => $question_text, 'order' => $index + 1 ]);

        if ($result !== false) {
            $question_ids[] = $wpdb->insert_id;
        }
    }

    if (empty($question_ids)) {
        wp_send_json_error('Failed to create questions');
    }

    wp_send_json_success(['question_ids' => $question_ids]);
}

/**
 * Add options to a question during onboarding
 */
function qb_onboarding_add_options() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    
    // Sanitize JSON input properly
    $options_json = isset($_POST['options']) ? sanitize_textarea_field(wp_unslash($_POST['options'])) : '';
    $raw_options = !empty($options_json) ? json_decode($options_json, true) : array();
    
    if (empty($raw_options) || !is_array($raw_options)) {
        wp_send_json_error('Options are required');
    }
    
    // Sanitize each option
    $options = array();
    foreach ($raw_options as $option) {
        $options[] = array(
            'text' => isset($option['text']) ? sanitize_text_field($option['text']) : '',
            'points' => isset($option['points']) ? intval($option['points']) : 0
        );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_options';

    foreach ($options as $option) {
        $option_text = $option['text'];
        $points = $option['points'];

        if (empty($option_text)) {
            continue;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($table_name, [ 'question_id' => $question_id, 'option_text' => $option_text, 'points' => $points ]);
    }

    wp_send_json_success(['message' => 'Options added successfully']);
}

/**
 * Add options to all questions during onboarding
 */
function qb_onboarding_add_all_options() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Handle question options data - could be array or JSON string
    $question_options = array();
    
    if (isset($_POST['question_options'])) {
        if (is_array($_POST['question_options'])) {
            // Direct array from JavaScript - need to recursively unslash and sanitize
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Will be sanitized below after unslashing
            $raw_question_options = wp_unslash($_POST['question_options']);
        } else {
            // JSON string
            $question_options_json = sanitize_textarea_field(wp_unslash($_POST['question_options']));
            $raw_question_options = !empty($question_options_json) ? json_decode($question_options_json, true) : array();
        }
    } else {
        $raw_question_options = array();
    }
    
    if (empty($raw_question_options) || !is_array($raw_question_options)) {
        wp_send_json_error('Question options are required');
    }
    
    // Sanitize question_options
    foreach ($raw_question_options as $question_id => $options) {
        $question_id_int = intval($question_id);
        $sanitized_options = array();
        if (is_array($options)) {
            foreach ($options as $option) {
                if (is_array($option)) {
                    $sanitized_options[] = array(
                        'text' => isset($option['text']) ? sanitize_text_field($option['text']) : '',
                        'points' => isset($option['points']) ? intval($option['points']) : 0
                    );
                }
            }
        }
        if (!empty($sanitized_options)) {
            $question_options[$question_id_int] = $sanitized_options;
        }
    }
    
    if (empty($question_options)) {
        wp_send_json_error('No valid question options provided');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_options';
    $total_options_added = 0;

    foreach ($question_options as $question_id => $options) {
        foreach ($options as $option) {
            $option_text = $option['text'];
            $points = $option['points'];
            
            if (empty($option_text)) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert($table_name, ['question_id' => $question_id,'option_text' => $option_text, 'points' => $points ]);

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

/**
 * AJAX handler for quiz submission (fallback for block editor)
 */
function qb_ajax_submit_quiz() {
    // Verify nonce
    $nonce = isset($_POST['qb_quiz_nonce']) ? sanitize_text_field(wp_unslash($_POST['qb_quiz_nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'qb_quiz_submission')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!isset($_POST['quiz_id'])) {
        wp_send_json_error('Missing quiz ID');
    }

    // Include the shortcodes frontend file for processing
    require_once QB_PATH . 'includes/shortcodes-frontend.php';
    
    // Set up the POST data for processing
    $_POST['action'] = 'qb_handle_quiz_submission';
    
    // Process quiz submission
    $result = qb_process_quiz_submission();
    
    if ($result['success']) {
        wp_send_json_success($result['data']);
    } else {
        wp_send_json_error($result['message']);
    }
}
