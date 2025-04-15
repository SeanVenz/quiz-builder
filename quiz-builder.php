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
if (file_exists(QB_PATH . 'includes/db-functions.php')) {
    require_once QB_PATH . 'includes/db-functions.php';
}

if (file_exists(QB_PATH . 'admin/quiz-admin-page.php')) {
    require_once QB_PATH . 'admin/quiz-admin-page.php';
}

if (file_exists(QB_PATH . 'admin/manage-questions-page.php')) {
    require_once QB_PATH . 'admin/manage-questions-page.php';
}

//registed activation hook
register_activation_hook(__FILE__, 'qb_activate_plugin');

function qb_activate_plugin() {
    if (function_exists('qb_create_quiz_table')) {
        qb_create_quiz_table();
    }

    if (function_exists('qb_create_questions_table')) {
        qb_create_questions_table();
    }

    if (function_exists('qb_create_options_table')) qb_create_options_table();
}

// Register the shortcode for displaying a quiz
function qb_display_quiz($atts) {
    global $wpdb;

    // Debugging message
    error_log('Shortcode "quiz_builder" triggered');

    // Shortcode attribute for quiz_id
    $atts = shortcode_atts([
        'quiz_id' => 0,
    ], $atts, 'quiz_builder');

    // Get quiz data
    $quiz_id = intval($atts['quiz_id']);
    $quiz_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';

    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));

    if (!$quiz) {
        return '<div class="error"><p>Quiz not found!</p></div>';
    }

    // Get questions for the quiz
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d", $quiz_id));

    // Generate the quiz HTML
    $output = '<div class="quiz-container">';
    $output .= '<h2>' . esc_html($quiz->title) . '</h2>';

    if ($questions) {
        $output .= '<form method="post" class="quiz-form" action="">';
        foreach ($questions as $question) {
            // Get the options for each question
            $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM $options_table WHERE question_id = %d", $question->id));

            $output .= '<div class="question">';
            $output .= '<p><strong>' . esc_html($question->question) . '</strong></p>';

            if ($options) {
                foreach ($options as $option) {
                    $output .= '<label>';
                    $output .= '<input type="radio" name="question_' . esc_attr($question->id) . '" value="' . esc_attr($option->id) . '" /> ';
                    $output .= esc_html($option->option_text);
                    $output .= '</label><br />';
                }
            }

            $output .= '</div>';
        }

        $output .= '<input type="submit" name="submit_quiz" value="Submit Quiz" class="button-primary" />';
        $output .= '</form>';
    } else {
        $output .= '<p>No questions available for this quiz.</p>';
    }

    $output .= '</div>';
    return $output;
}

add_shortcode('quiz_builder', 'qb_display_quiz');


// Handle form submission and redirect to results page
function qb_handle_quiz_submission() {
    global $wpdb;

    if (isset($_POST['submit_quiz'])) {
        $quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
        $quiz_table = $wpdb->prefix . 'qb_quizzes';
        $questions_table = $wpdb->prefix . 'qb_questions';
        $options_table = $wpdb->prefix . 'qb_options';

        // Get the quiz
        $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));

        if (!$quiz) {
            return '<div class="error"><p>Quiz not found!</p></div>';
        }

        // Initialize score
        $score = 0;

        // Check each question and calculate the score
        $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d", $quiz_id));
        foreach ($questions as $question) {
            $selected_option_id = isset($_POST['question_' . $question->id]) ? intval($_POST['question_' . $question->id]) : 0;

            if ($selected_option_id) {
                // Get the selected option
                $selected_option = $wpdb->get_row($wpdb->prepare("SELECT * FROM $options_table WHERE id = %d", $selected_option_id));

                // Add points for the selected option
                if ($selected_option) {
                    $score += $selected_option->points;
                }
            }
        }

        // Build redirect URL and check
        $redirect_url = add_query_arg(['quiz_id' => $quiz_id, 'score' => $score], home_url('/quiz-results/'));
        error_log('Redirect URL: ' . $redirect_url); // Debugging

        // Redirect to results page
        wp_redirect($redirect_url);
        exit;
    }
}

add_action('wp', 'qb_handle_quiz_submission');


// Create a page template to show the results

function qb_display_quiz_results() {
    if (isset($_GET['quiz_id']) && isset($_GET['score'])) {
        $quiz_id = intval($_GET['quiz_id']);
        $score = intval($_GET['score']);

        // Get quiz data
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'qb_quizzes';
        $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));

        if (!$quiz) {
            return '<div class="error"><p>Quiz not found!</p></div>';
        }

        // Display the results
        $output = '<div class="quiz-result">';
        $output .= '<h3>' . esc_html($quiz->title) . ' - Results</h3>';
        $output .= '<p>Your score: <strong>' . esc_html($score) . '</strong></p>';
        $output .= '</div>';

        return $output;
    }
}

add_shortcode('quiz_results', 'qb_display_quiz_results');


function qb_register_shortcode() {
    add_shortcode('quiz_builder', 'qb_display_quiz');
    add_shortcode('quiz_results', 'qb_display_quiz_results');
}

add_action('init', 'qb_register_shortcode');


// Add admin menu
add_action('admin_menu', 'qb_add_admin_menu');
