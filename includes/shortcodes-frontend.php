<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend shortcodes and handlers
 */

// Register shortcodes
add_action('init', 'qb_register_shortcodes', 10);
add_action('template_redirect', 'qb_handle_quiz_submission', 1);

// Register the query var
add_filter('query_vars', 'qb_register_query_vars');

// Add rewrite rules
add_action('init', 'qb_add_rewrite_rules');

/**
 * Register shortcodes
 */
function qb_register_shortcodes() {
    add_shortcode('quiz_builder', 'qb_display_quiz');
    add_shortcode('quiz_results', 'qb_display_quiz_results');
}

/**
 * Display quiz shortcode handler
 */
function qb_display_quiz($atts) {
    global $wpdb;

    // Enqueue jQuery for form handling
    wp_enqueue_script('jquery');

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

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));
    if (!$quiz) {
        return '<div class="error"><p>Quiz not found!</p></div>';
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d ORDER BY `order` ASC, id ASC", $quiz_id));
    if (!$questions) {
        return '<div class="error"><p>No questions available for this quiz.</p></div>';
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM $options_table WHERE question_id IN (SELECT id FROM $questions_table WHERE quiz_id = %d)", $quiz_id));

    // Get quiz settings
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM $settings_table WHERE quiz_id = %d", $quiz_id));
    if (!$settings) {
        $settings = (object) array(
            'is_paginated' => 0,
            'questions_per_page' => 1,
            'randomize_questions' => 0,
            'randomize_answers' => 0
        );
    }

    // Handle randomization logic
    $user_identifier = qb_get_user_identifier($quiz_id);
    $quiz_transient_key = 'qb_questions_' . $quiz_id . '_' . $user_identifier;
    $options_transient_key = 'qb_options_' . $quiz_id . '_' . $user_identifier;
    
    $is_fresh_start = qb_is_fresh_quiz_start($quiz_transient_key);
    
    if ($is_fresh_start) {
        qb_clear_quiz_session($quiz_id, $quiz_transient_key, $options_transient_key);
    }
    
    // Apply randomization
    $questions = qb_apply_question_randomization($questions, $settings, $quiz_transient_key);
    $options = qb_apply_answer_randomization($options, $settings, $options_transient_key);

    return qb_get_quiz_display($quiz, $questions, $options, $settings);
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
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $attempts_table WHERE random_id = %s", $random_id));
    if (!$attempt) {
        return '<div class="error"><p>Quiz results not found.</p></div>';
    }

    // Get the quiz
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
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
        require_once QB_PATH . 'includes/results/class-quiz-results-display.php';
        $results_display = new QB_Quiz_Results_Display();
        return $results_display->display_results($attempt->id);
    } else {
        // Use basic template output
        $score = $attempt->score;
        $total_possible_points = $attempt->total_points;
        
        // Parse user answers for category scores
        $user_answers = json_decode($attempt->answers, true);
        $answer_map = array();
        if ($user_answers) {
            foreach ($user_answers as $answer) {
                $answer_map[$answer['question_id']] = $answer['option_id'];
            }
        }
        
        // Include results template
        require_once QB_PATH . 'templates/quiz-results.php';
        return qb_get_quiz_results($quiz, $score, $total_possible_points, $answer_map, $attempt->id);
    }
}

/**
 * Handle quiz submission
 */
function qb_handle_quiz_submission() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'qb_handle_quiz_submission') {
        return;
    }

    // Verify nonce
    $nonce = isset($_POST['qb_quiz_nonce']) ? sanitize_text_field(wp_unslash($_POST['qb_quiz_nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'qb_quiz_submission')) {
        if (wp_doing_ajax()) {
            wp_send_json_error('Invalid nonce');
        }
        return;
    }

    if (!isset($_POST['quiz_id'])) {
        if (wp_doing_ajax()) {
            wp_send_json_error('Missing quiz ID');
        }
        return;
    }

    // Process quiz submission
    $result = qb_process_quiz_submission();
    
    if (wp_doing_ajax()) {
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    } else {
        if ($result['success']) {
            wp_safe_redirect($result['data']['redirect_url']);
            exit;
        } else {
            $redirect_url = wp_get_referer() ? wp_get_referer() : home_url();
            $redirect_url = add_query_arg('quiz_error', $result['message'], $redirect_url);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

/**
 * Process quiz submission and calculate score
 */
function qb_process_quiz_submission() {    global $wpdb;
    
    // Validate quiz_id exists
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling function qb_handle_quiz_submission
    if (!isset($_POST['quiz_id'])) {
        return array('success' => false, 'message' => 'Missing quiz ID');
    }
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling function qb_handle_quiz_submission
    $quiz_id = intval($_POST['quiz_id']);
    $quiz_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));
    if (!$quiz) {
        return array('success' => false, 'message' => 'Quiz not found');
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d", $quiz_id));
    
    // Validate required questions
    $validation_result = qb_validate_required_questions($questions);
    if (!$validation_result['success']) {
        return $validation_result;
    }

    // Calculate score
    $score_data = qb_calculate_quiz_score($questions, $options_table);
    
    // Generate random ID
    $random_id = qb_generate_random_id();

    // Get user ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = null;
    }

    // Store the attempt
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $insert_result = $wpdb->insert($attempts_table, array(
        'random_id' => $random_id,
        'quiz_id' => $quiz_id,
        'user_id' => $user_id,
        'score' => $score_data['score'],
        'total_points' => $score_data['total_points'],
        'answers' => json_encode($score_data['answers']),
        'created_at' => current_time('mysql')
    ));

    if ($insert_result === false) {
        return array('success' => false, 'message' => 'Failed to save quiz attempt');
    }

    // Clear session data
    qb_clear_quiz_session_after_completion($quiz_id);

    // Get redirect URL
    $redirect_url = qb_get_results_redirect_url($random_id);
    
    return array(
        'success' => true,
        'data' => array('redirect_url' => $redirect_url)
    );
}

/**
 * Helper functions for quiz processing
 */
function qb_get_user_identifier($quiz_id) {
    $user_identifier = get_current_user_id();
    if (!$user_identifier) {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $browser_fingerprint = md5($remote_addr . $user_agent);
        $quiz_session_option = 'qb_quiz_session_' . $quiz_id . '_' . $browser_fingerprint;
        $user_identifier = get_option($quiz_session_option);
        
        if (!$user_identifier) {
            $user_identifier = 'guest_' . time() . '_' . wp_rand(1000, 9999);
            update_option($quiz_session_option, $user_identifier, false);
        }
    }
    return $user_identifier;
}

function qb_is_fresh_quiz_start($quiz_transient_key) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter check for quiz navigation, not sensitive data processing
    if (!isset($_GET['quiz_page']) && !isset($_GET['reset_quiz'])) {
        $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        if (empty($referer) || strpos($referer, 'quiz_page=') === false) {
            return true;
        }
    }
    
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter check for quiz reset, not sensitive data processing
    if (isset($_GET['reset_quiz'])) {
        return true;
    }
    
    return false;
}

function qb_clear_quiz_session($quiz_id, $quiz_transient_key, $options_transient_key) {
    delete_transient($quiz_transient_key);
    delete_transient($options_transient_key);
    
    if (!get_current_user_id()) {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $browser_fingerprint = md5($remote_addr . $user_agent);
        $quiz_session_option = 'qb_quiz_session_' . $quiz_id . '_' . $browser_fingerprint;
        delete_option($quiz_session_option);
    }
}

function qb_apply_question_randomization($questions, $settings, $quiz_transient_key) {
    if (!isset($settings->randomize_questions) || !$settings->randomize_questions) {
        return $questions;
    }
    
    $stored_order = get_transient($quiz_transient_key);
    if ($stored_order && is_array($stored_order)) {
        $questions_by_id = array();
        foreach ($questions as $question) {
            $questions_by_id[$question->id] = $question;
        }
        
        $reordered_questions = array();
        foreach ($stored_order as $question_id) {
            if (isset($questions_by_id[$question_id])) {
                $reordered_questions[] = $questions_by_id[$question_id];
            }
        }
        return $reordered_questions;
    } else {
        shuffle($questions);
        $question_ids = array_map(function($q) { return $q->id; }, $questions);
        set_transient($quiz_transient_key, $question_ids, 3600);
        return $questions;
    }
}

function qb_apply_answer_randomization($options, $settings, $options_transient_key) {
    if (!isset($settings->randomize_answers) || !$settings->randomize_answers) {
        return $options;
    }
    
    $stored_options_order = get_transient($options_transient_key);
    if ($stored_options_order && is_array($stored_options_order)) {
        $options_by_id = array();
        foreach ($options as $option) {
            $options_by_id[$option->id] = $option;
        }
        
        $reordered_options = array();
        foreach ($stored_options_order as $option_id) {
            if (isset($options_by_id[$option_id])) {
                $reordered_options[] = $options_by_id[$option_id];
            }
        }
        return $reordered_options;
    } else {
        // Group options by question and randomize each group
        $options_by_question = array();
        foreach ($options as $option) {
            if (!isset($options_by_question[$option->question_id])) {
                $options_by_question[$option->question_id] = array();
            }
            $options_by_question[$option->question_id][] = $option;
        }
        
        foreach ($options_by_question as $question_id => $question_options) {
            shuffle($options_by_question[$question_id]);
        }
        
        $options = array();
        $options_order = array();
        foreach ($options_by_question as $question_options) {
            foreach ($question_options as $option) {
                $options[] = $option;
                $options_order[] = $option->id;
            }
        }
        
        set_transient($options_transient_key, $options_order, 3600);
        return $options;
    }
}

function qb_validate_required_questions($questions) {
    $required_questions = array_filter($questions, function($q) { 
        return isset($q->required) && $q->required; 
    });
    
    $unanswered_required = array();
    foreach ($required_questions as $req_question) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Form data validated in calling function qb_handle_quiz_submission
        $selected_option_id = isset($_POST['question_' . $req_question->id]) ? intval($_POST['question_' . $req_question->id]) : 0;
        if (!$selected_option_id) {
            $unanswered_required[] = $req_question->question;
        }
    }
    
    if (!empty($unanswered_required)) {
        return array(
            'success' => false, 
            'message' => 'Please answer all required questions before submitting.'
        );
    }
    
    return array('success' => true);
}

function qb_calculate_quiz_score($questions, $options_table) {
    global $wpdb;
    
    $score = 0;
    $answers = array();
    
    foreach ($questions as $question) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Form data validated in calling function qb_handle_quiz_submission
        $selected_option_id = isset($_POST['question_' . $question->id]) ? intval($_POST['question_' . $question->id]) : 0;
        if ($selected_option_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,  WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,  WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $total_possible_points = $wpdb->get_var($wpdb->prepare(" SELECT SUM(max_points) FROM ( SELECT MAX(points) as max_points  FROM $options_table o  JOIN {$wpdb->prefix}qb_questions q ON o.question_id = q.id  WHERE q.quiz_id = %d  GROUP BY q.id ) as question_max_points", $questions[0]->quiz_id));
    
    return array(
        'score' => $score,
        'total_points' => $total_possible_points,
        'answers' => $answers
    );
}

function qb_clear_quiz_session_after_completion($quiz_id) {
    if (session_status() != PHP_SESSION_DISABLED) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $quiz_session_key = 'qb_randomized_questions_' . $quiz_id;
        $options_session_key = 'qb_randomized_options_' . $quiz_id;
        unset($_SESSION[$quiz_session_key]);
        unset($_SESSION[$options_session_key]);
    }
}

function qb_get_results_redirect_url($random_id) {
    $results_page = get_page_by_path('quiz-results');
    if ($results_page) {
        return get_permalink($results_page) . $random_id . '/';
    } else {
        return home_url('/quiz-results/' . $random_id . '/');
    }
}

function qb_generate_random_id() {
    return bin2hex(random_bytes(16));
}

/**
 * Register query vars
 */
function qb_register_query_vars($vars) {
    $vars[] = 'quiz_result_id';
    return $vars;
}

/**
 * Add rewrite rules
 */
function qb_add_rewrite_rules() {
    add_rewrite_rule(
        '^quiz-results/([^/]+)/?$',
        'index.php?pagename=quiz-results&quiz_result_id=$matches[1]',
        'top'
    );
}
