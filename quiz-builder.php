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

/**
 * Handle quiz submission and redirect to results
 */
function qb_handle_quiz_submission() {
    if (!isset($_POST['submit_quiz']) || !isset($_POST['quiz_id'])) {
        return;
    }

    global $wpdb;
    $quiz_id = intval($_POST['quiz_id']);
    $quiz_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';

    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));
    if (!$quiz) {
        return;
    }

    $score = 0;
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d", $quiz_id));

    foreach ($questions as $question) {
        $selected_option_id = isset($_POST['question_' . $question->id]) ? intval($_POST['question_' . $question->id]) : 0;
        if ($selected_option_id) {
            $selected_option = $wpdb->get_row($wpdb->prepare("SELECT * FROM $options_table WHERE id = %d", $selected_option_id));
            if ($selected_option) {
                $score += $selected_option->points;
            }
        }
    }

    // Redirect to results page
    $redirect_url = add_query_arg([
        'quiz_id' => $quiz_id,
        'score' => $score
    ], home_url('/quiz-results/'));

    wp_redirect($redirect_url);
    exit;
}

/**
 * Display quiz results
 */
function qb_display_quiz_results() {
    if (!isset($_GET['quiz_id']) || !isset($_GET['score'])) {
        return '<div class="error"><p>No quiz results to display.</p></div>';
    }

    global $wpdb;
    $quiz_id = intval($_GET['quiz_id']);
    $score = intval($_GET['score']);
    $quiz_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';
    
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));
    if (!$quiz) {
        return '<div class="error"><p>Quiz not found!</p></div>';
    }

    // Get total possible points by summing the maximum points for each question
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

    return qb_get_quiz_results($quiz, $score, $total_possible_points);
}

// Register shortcodes
function qb_register_shortcodes() {
    add_shortcode('quiz_builder', 'qb_display_quiz');
    add_shortcode('quiz_results', 'qb_display_quiz_results');
}
add_action('init', 'qb_register_shortcodes', 10);

// Add form submission handler
add_action('template_redirect', 'qb_handle_quiz_submission');

// Add admin menu
add_action('admin_menu', 'qb_add_admin_menu');
