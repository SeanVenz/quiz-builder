<?php
/**
 * Template for displaying quiz
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display quiz template
 */
function qb_get_quiz_display($quiz, $questions, $options) {
    $output = '<div class="quiz-container">';
    $output .= '<h2>' . esc_html($quiz->title) . '</h2>';
    $output .= '<form method="post" class="quiz-form">';
    $output .= wp_nonce_field('qb_quiz_submission', 'qb_quiz_nonce', true, false);
    $output .= '<input type="hidden" name="action" value="qb_handle_quiz_submission">';
    $output .= '<input type="hidden" name="quiz_id" value="' . esc_attr($quiz->id) . '">';

    foreach ($questions as $question) {
        $question_options = array_filter($options, function($option) use ($question) {
            return $option->question_id == $question->id;
        });

        $output .= '<div class="question">';
        $output .= '<p><strong>' . esc_html($question->question) . '</strong></p>';

        if ($question_options) {
            foreach ($question_options as $option) {
                $output .= '<label>';
                $output .= '<input type="radio" name="question_' . esc_attr($question->id) . '" value="' . esc_attr($option->id) . '" required /> ';
                $output .= esc_html($option->option_text);
                $output .= '</label><br />';
            }
        }

        $output .= '</div>';
    }

    $output .= '<input type="submit" name="submit_quiz" value="Submit Quiz" class="button-primary" />';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
} 