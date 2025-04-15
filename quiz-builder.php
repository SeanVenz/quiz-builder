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

// Include dependencies
require_once QB_PATH . 'includes/db-functions.php';
require_once QB_PATH . 'admin/quiz-admin-page.php';
require_once QB_PATH . 'admin/manage-questions-page.php';
require_once QB_PATH . 'templates/quiz-display.php';
require_once QB_PATH . 'templates/quiz-results.php';

// Register activation hook
register_activation_hook(__FILE__, 'qb_activate_plugin');

/**
 * Plugin activation function
 */
function qb_activate_plugin() {
    if (function_exists('qb_create_quiz_table')) {
        qb_create_quiz_table();
    }

    if (function_exists('qb_create_questions_table')) {
        qb_create_questions_table();
    }

    if (function_exists('qb_create_options_table')) {
        qb_create_options_table();
    }

    if (function_exists('qb_create_attempts_table')) {
        qb_create_attempts_table();
    }

    // Create quiz results page if it doesn't exist
    $results_page = get_page_by_path('quiz-results');
    if (!$results_page) {
        $page_data = array(
            'post_title'    => 'Quiz Results',
            'post_name'     => 'quiz-results',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '[quiz_results]'
        );
        wp_insert_post($page_data);
    }
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

    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));
    if (!$quiz) {
        return '<div class="error"><p>Quiz not found!</p></div>';
    }

    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d", $quiz_id));
    if (!$questions) {
        return '<div class="error"><p>No questions available for this quiz.</p></div>';
    }

    $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM $options_table WHERE question_id IN (SELECT id FROM $questions_table WHERE quiz_id = %d)", $quiz_id));

    return qb_get_quiz_display($quiz, $questions, $options);
}

function qb_generate_random_id() {
    return bin2hex(random_bytes(16));
}

function qb_handle_quiz_submission() {
    // Debug logging
    error_log('Quiz submission handler started');
    error_log('POST data: ' . print_r($_POST, true));

    // Verify nonce
    if (!isset($_POST['qb_quiz_nonce']) || !wp_verify_nonce($_POST['qb_quiz_nonce'], 'qb_quiz_submission')) {
        error_log('Invalid nonce');
        return;
    }

    if (!isset($_POST['submit_quiz']) || !isset($_POST['quiz_id'])) {
        error_log('Missing required POST data: submit_quiz or quiz_id');
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

// Register the action for both logged-in and non-logged-in users
add_action('template_redirect', function() {
    if (isset($_POST['action']) && $_POST['action'] === 'qb_handle_quiz_submission') {
        qb_handle_quiz_submission();
    }
});

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
    }

    // Get the quiz
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quizzes_table WHERE id = %d", $attempt->quiz_id));
    if (!$quiz) {
        return '<div class="error"><p>Quiz not found.</p></div>';
    }

    return qb_get_quiz_results($quiz, $attempt->score, $attempt->total_points);
}

// Register shortcodes
function qb_register_shortcodes() {
    add_shortcode('quiz_builder', 'qb_display_quiz');
    add_shortcode('quiz_results', 'qb_display_quiz_results');
}
add_action('init', 'qb_register_shortcodes', 10);

// Add form submission handler with higher priority
add_action('template_redirect', 'qb_handle_quiz_submission', 1);

// Add admin menu
add_action('admin_menu', 'qb_add_admin_menu');

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
    
    $output = '<div class="attempt-details">';
    $output .= '<h2>Quiz Attempt Details</h2>';
    
    // General information
    $output .= '<div class="attempt-info">';
    $output .= '<p><strong>Quiz:</strong> ' . esc_html($quiz->title) . '</p>';
    $output .= '<p><strong>User:</strong> ' . esc_html($user ? $user->display_name : 'Guest') . '</p>';
    $output .= '<p><strong>Score:</strong> ' . esc_html($attempt->score) . '/' . esc_html($attempt->total_points) . ' (' . round(($attempt->score / $attempt->total_points) * 100) . '%)</p>';
    $output .= '<p><strong>Date:</strong> ' . esc_html($attempt->created_at) . '</p>';
    $output .= '</div>';
    
    // Answers table
    $output .= '<h3>Answers</h3>';
    $output .= '<table class="widefat fixed striped">';
    $output .= '<thead><tr><th>Question</th><th>Answer</th><th>Points</th></tr></thead>';
    $output .= '<tbody>';

    foreach ($answers as $answer) {
        $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM $questions_table WHERE id = %d", $answer['question_id']));
        $option = $wpdb->get_row($wpdb->prepare("SELECT * FROM $options_table WHERE id = %d", $answer['option_id']));
        
        if ($question && $option) {
            $output .= '<tr>';
            $output .= '<td>' . esc_html($question->question) . '</td>';
            $output .= '<td>' . esc_html($option->option_text) . '</td>';
            $output .= '<td>' . esc_html($option->points) . '</td>';
            $output .= '</tr>';
        }
    }

    $output .= '</tbody></table>';
    $output .= '<p style="margin-top: 20px;"><a href="#" class="button" onclick="document.getElementById(\'attempt-details-modal\').style.display=\'none\';">Close</a></p>';
    $output .= '</div>';

    wp_send_json_success(array('html' => $output));
}

// Add rewrite rules for the new URL structure
function qb_add_rewrite_rules() {
    // Add rule for page-based URLs
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
