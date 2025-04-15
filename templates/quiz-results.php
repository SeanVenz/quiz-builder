<?php
/**
 * Template for displaying quiz results
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display quiz results template
 */
function qb_get_quiz_results($quiz, $score, $total_possible_points) {
    $percentage = $total_possible_points > 0 ? round(($score / $total_possible_points) * 100) : 0;

    $output = '<div class="quiz-result" style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    $output .= '<h2 style="color: #333; margin-bottom: 20px; text-align: center;">Quiz Results</h2>';
    $output .= '<h3 style="color: #444; margin-bottom: 15px; text-align: center;">' . esc_html($quiz->title) . '</h3>';
    
    $output .= '<div style="margin-bottom: 20px; text-align: center;">';
    $output .= '<p style="font-size: 24px; margin-bottom: 10px;">Your Score: <strong>' . esc_html($score) . '/' . esc_html($total_possible_points) . '</strong></p>';
    $output .= '<p style="font-size: 20px;">Percentage: <strong>' . esc_html($percentage) . '%</strong></p>';
    $output .= '</div>';

    $output .= '<div style="margin-top: 20px; text-align: center;">';
    $output .= '<a href="' . esc_url(add_query_arg('quiz_id', $quiz->id, home_url())) . '" class="button button-primary" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">Retake Quiz</a>';
    $output .= '</div>';
    
    $output .= '</div>';

    return $output;
} 