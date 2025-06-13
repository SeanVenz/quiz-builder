<?php
/**
 * Template for displaying quiz results
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calculate category scores for a quiz attempt
 */
function qb_calculate_category_scores($quiz_id, $user_answers) {
    global $wpdb;
    
    $questions_table = $wpdb->prefix . 'qb_questions';
    $categories_table = $wpdb->prefix . 'qb_categories';
    $options_table = $wpdb->prefix . 'qb_options';
    
    // Get all questions with their categories
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT q.id, q.question, q.category_id, c.name as category_name, c.color as category_color
         FROM $questions_table q 
         LEFT JOIN $categories_table c ON q.category_id = c.id 
         WHERE q.quiz_id = %d 
         ORDER BY q.`order` ASC",
        $quiz_id
    ));
    
    $category_scores = array();
    $category_totals = array();
    
    foreach ($questions as $question) {
        $category_name = $question->category_name ?: 'Uncategorized';
        $category_id = $question->category_id ?: 'uncategorized';
        $category_color = $question->category_color ?: '#666666';
        
        // Initialize category if not exists
        if (!isset($category_scores[$category_id])) {
            $category_scores[$category_id] = array(
                'name' => $category_name,
                'color' => $category_color,
                'score' => 0,
                'total' => 0,
                'questions' => array()
            );
        }
        
        // Get the maximum points for this question
        $max_points = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(points) FROM $options_table WHERE question_id = %d",
            $question->id
        )) ?: 0;
        
        $category_scores[$category_id]['total'] += $max_points;
        
        // Add question info to category
        $question_score = 0;
        if (isset($user_answers[$question->id])) {
            $selected_option_id = $user_answers[$question->id];
            $question_score = $wpdb->get_var($wpdb->prepare(
                "SELECT points FROM $options_table WHERE id = %d",
                $selected_option_id
            )) ?: 0;
        }
        
        $category_scores[$category_id]['score'] += $question_score;
        $category_scores[$category_id]['questions'][] = array(
            'question' => $question->question,
            'score' => $question_score,
            'max_points' => $max_points
        );
    }
    
    return $category_scores;
}

/**
 * Display quiz results template
 */
/**
 * Display quiz results template
 */
function qb_get_quiz_results($quiz, $score, $total_possible_points, $user_answers = array(), $attempt_id = null) {
    error_log('Displaying quiz results for quiz ID: ' . $quiz->id);
    error_log('Score: ' . $score . ' out of ' . $total_possible_points);

    $percentage = $total_possible_points > 0 ? round(($score / $total_possible_points) * 100) : 0;

    // Check if category scores should be displayed
    require_once plugin_dir_path(__FILE__) . '../includes/db/class-quiz-settings-db.php';
    $settings_db = new QB_Quiz_Settings_DB();
    $settings = $settings_db->get_settings($quiz->id);
    $show_category_scores = $settings && $settings->show_category_scores;
    $allow_pdf_export = $settings && $settings->allow_pdf_export;

    $output = '<div class="quiz-result" style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    $output .= '<h2 style="color: #333; margin-bottom: 20px; text-align: center;">Quiz Results</h2>';
    $output .= '<h3 style="color: #444; margin-bottom: 15px; text-align: center;">' . esc_html($quiz->title) . '</h3>';
    
    $output .= '<div style="margin-bottom: 20px; text-align: center;">';
    $output .= '<p style="font-size: 24px; margin-bottom: 10px;">Your Score: <strong>' . esc_html($score) . '/' . esc_html($total_possible_points) . '</strong></p>';
    $output .= '<p style="font-size: 20px;">Percentage: <strong>' . esc_html($percentage) . '%</strong></p>';
    $output .= '</div>';

    // Display category scores if enabled
    if ($show_category_scores && !empty($user_answers)) {
        $category_scores = qb_calculate_category_scores($quiz->id, $user_answers);
        
        if (!empty($category_scores)) {
            $output .= '<div style="margin: 30px 0; padding: 20px; background: white; border-radius: 6px; border: 1px solid #ddd;">';
            $output .= '<h4 style="color: #333; margin-bottom: 15px; text-align: center;">Category Breakdown</h4>';
            
            foreach ($category_scores as $category_id => $category) {
                $cat_percentage = $category['total'] > 0 ? round(($category['score'] / $category['total']) * 100) : 0;
                
                $output .= '<div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid ' . esc_attr($category['color']) . ';">';
                $output .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
                $output .= '<span style="font-weight: bold; color: #333;">' . esc_html($category['name']) . '</span>';
                $output .= '<span style="font-weight: bold;">' . esc_html($category['score']) . '/' . esc_html($category['total']) . ' (' . esc_html($cat_percentage) . '%)</span>';
                $output .= '</div>';
                
                // Progress bar
                $output .= '<div style="width: 100%; background-color: #e0e0e0; border-radius: 10px; height: 8px; margin-bottom: 10px;">';
                $output .= '<div style="width: ' . esc_attr($cat_percentage) . '%; background-color: ' . esc_attr($category['color']) . '; height: 8px; border-radius: 10px; transition: width 0.3s ease;"></div>';
                $output .= '</div>';
                
                // Individual questions (collapsible)
                if (count($category['questions']) > 1) {
                    $output .= '<details style="margin-top: 10px;">';
                    $output .= '<summary style="cursor: pointer; color: #666; font-size: 12px;">View ' . count($category['questions']) . ' questions</summary>';
                    $output .= '<div style="margin-top: 8px; padding-left: 15px;">';
                    
                    foreach ($category['questions'] as $question) {
                        $q_percentage = $question['max_points'] > 0 ? round(($question['score'] / $question['max_points']) * 100) : 0;
                        $output .= '<div style="font-size: 12px; color: #666; margin-bottom: 4px;">';
                        $output .= '<span>' . esc_html(wp_trim_words($question['question'], 8)) . '</span>';
                        $output .= '<span style="float: right;">' . esc_html($question['score']) . '/' . esc_html($question['max_points']) . '</span>';
                        $output .= '</div>';
                    }
                    
                    $output .= '</div>';
                    $output .= '</details>';
                }
                
                $output .= '</div>';
            }
            
            $output .= '</div>';
        }
    }

    // Add PDF export button if enabled and attempt ID is available
    if ($allow_pdf_export && $attempt_id) {
        $export_url = admin_url('admin-ajax.php') . '?' . http_build_query([
            'action' => 'qb_export_pdf',
            'attempt_id' => $attempt_id,
            'nonce' => wp_create_nonce('qb_pdf_export_' . $attempt_id)
        ]);

        $output .= '<div class="quiz-pdf-export" style="margin: 20px 0; text-align: center;">';
        $output .= '<a href="' . esc_url($export_url) . '" class="button button-primary" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-right: 10px;">';
        $output .= '📄 Download PDF Results</a>';
        $output .= '</div>';
    }

    $output .= '<div style="margin-top: 20px; text-align: center;">';
    $output .= '<a href="' . esc_url(add_query_arg('quiz_id', $quiz->id, home_url())) . '" class="button button-primary" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">Retake Quiz</a>';
    $output .= '</div>';
    
    $output .= '</div>';

    return $output;
}