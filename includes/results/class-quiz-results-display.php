<?php
if (!defined('ABSPATH')) exit;

class QB_Quiz_Results_Display {
    private $wpdb;
    private $settings_db;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        require_once plugin_dir_path(__FILE__) . '../db/class-quiz-settings-db.php';
        $this->settings_db = new QB_Quiz_Settings_DB();
    }

    /**
     * Display quiz results including user answers if enabled
     */
    public function display_results($attempt_id) {
        $attempt = $this->get_attempt_details($attempt_id);
        if (!$attempt) {
            return '<p>No results found.</p>';
        }

        $quiz_id = $attempt->quiz_id;
        $settings = $this->settings_db->get_settings($quiz_id);
        $quiz = $this->get_quiz_details($quiz_id);
        
        if (!$quiz) {
            return '<p>Quiz not found.</p>';
        }

        $percentage = round(($attempt->score / $attempt->total_points) * 100);
        
        $output = sprintf(
            '<div class="quiz-results">
                <h2>%s Results</h2>
                <div class="quiz-score">
                    <p>Your Score: %d out of %d (%d%%)</p>
                </div>',
            esc_html($quiz->title),
            $attempt->score,
            $attempt->total_points,
            $percentage
        );        // Show category scores if enabled
        if ($settings && $settings->show_category_scores) {
            $output .= $this->get_category_scores($attempt_id, $quiz_id);
        }

        // Only show detailed results if enabled in settings
        if ($settings && $settings->show_user_answers) {
            $output .= $this->get_detailed_results($attempt_id);
        }

        // Add PDF export button if enabled in settings
        if ($settings && $settings->allow_pdf_export) {
            $output .= $this->get_pdf_export_button($attempt_id);
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get detailed results including user answers
     */
    private function get_detailed_results($attempt_id) {
        $answers = $this->get_attempt_answers($attempt_id);
        if (empty($answers)) {
            return '<p>No answers found.</p>';
        }

        $output = '<div class="quiz-answers">
            <h3>Your Answers</h3>
            <table class="quiz-answers-table">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Your Answer</th>
                        <th>Correct Answer</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($answers as $answer) {
            $is_correct = $answer->selected_option == $answer->correct_option;
            $output .= sprintf(
                '<tr class="%s">
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                $is_correct ? 'correct-answer' : 'incorrect-answer',
                esc_html($answer->question_text),
                esc_html($answer->selected_text),
                esc_html($answer->correct_text),
                $is_correct ? '✓' : '✗'
            );
        }

        $output .= '</tbody></table></div>';
        return $output;
    }    /**
     * Get attempt details from database
     */
    private function get_attempt_details($attempt_id) {
        $attempts_table = $this->wpdb->prefix . 'qb_attempts';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is set internally and safe in this context.
        return $this->wpdb->get_row($this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name is set internally and safe in this context.
            "SELECT * FROM `{$attempts_table}` WHERE id = %d", $attempt_id));
    }    /**
     * Get quiz details
     */
    private function get_quiz_details($quiz_id) {
        $quizzes_table = $this->wpdb->prefix . 'qb_quizzes';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is set internally and safe in this context.
        return $this->wpdb->get_row($this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name is set internally and safe in this context.
            "SELECT * FROM `{$quizzes_table}` WHERE id = %d", $quiz_id));
    }    /**
     * Get attempt answers with question and option details
     */
    public function get_attempt_answers($attempt_id) {
        $attempt = $this->get_attempt_details($attempt_id);
        if (!$attempt) {
            return array();
        }

        $answers_data = json_decode($attempt->answers, true);
        if (empty($answers_data)) {
            return array();
        }

        // Get all question IDs from the answers
        $question_ids = array();
        foreach ($answers_data as $data) {
            if (isset($data['question_id'])) {
                $question_ids[] = $data['question_id'];
            }
        }

        if (empty($question_ids)) {
            return array();
        }        // Create placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        
        // Prepare table names
        $questions_table = $this->wpdb->prefix . 'qb_questions';
        $options_table = $this->wpdb->prefix . 'qb_options';
        
        // Get questions with their correct options (highest points option is correct)
        $sql = "SELECT q.id, q.question, 
                       MAX(o.points) as max_points,
                       o2.id as correct_option,
                       o2.option_text as correct_text
                FROM `{$questions_table}` q
                LEFT JOIN `{$options_table}` o ON q.id = o.question_id
                LEFT JOIN `{$options_table}` o2 ON q.id = o2.question_id AND o.points = o2.points
                WHERE q.id IN ($placeholders)
                GROUP BY q.id, q.question
                ORDER BY q.order ASC";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table and placeholders are set internally and safe in this context.
        $questions = $this->wpdb->get_results($this->wpdb->prepare($sql, $question_ids));

        if (!$questions) {
            return array();
        }

        // Get all selected option IDs
        $selected_option_ids = array();
        foreach ($answers_data as $data) {
            if (isset($data['option_id'])) {
                $selected_option_ids[] = $data['option_id'];
            }
        }

        // Get all selected options in one query
        $selected_options = array();
        if (!empty($selected_option_ids)) {
            $option_placeholders = implode(',', array_fill(0, count($selected_option_ids), '%d'));
            $options_sql = "SELECT id, option_text 
                           FROM `{$options_table}` 
                           WHERE id IN ($option_placeholders)";
            
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table and placeholders are set internally and safe in this context.
            $options = $this->wpdb->get_results($this->wpdb->prepare($options_sql, $selected_option_ids));
            
            foreach ($options as $option) {
                $selected_options[$option->id] = $option->option_text;
            }
        }

        $results = array();
        foreach ($questions as $question) {
            foreach ($answers_data as $answer_data) {
                if ($answer_data['question_id'] == $question->id) {
                    $result = new stdClass();
                    $result->question_text = $question->question;
                    $result->selected_option = $answer_data['option_id'];
                    $result->selected_text = isset($selected_options[$answer_data['option_id']]) 
                        ? $selected_options[$answer_data['option_id']] 
                        : 'Unknown';
                    $result->correct_option = $question->correct_option;
                    $result->correct_text = $question->correct_text;
                    $results[] = $result;
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Generate PDF export button
     */
    private function get_pdf_export_button($attempt_id) {
        $attempt = $this->get_attempt_details($attempt_id);
        if (!$attempt) {
            return '';
        }

        $export_url = admin_url('admin-ajax.php') . '?' . http_build_query([
            'action' => 'qb_export_pdf',
            'attempt_id' => $attempt_id,
            'nonce' => wp_create_nonce('qb_pdf_export_' . $attempt_id)
        ]);

        return sprintf(
            '<div class="quiz-pdf-export" style="margin-top: 20px;">
                <a href="%s" class="button button-primary" target="_blank">
                    📄 Download PDF Results
                </a>
            </div>',
            esc_url($export_url)        );
    }

    /**
     * Get category scores breakdown
     */
    private function get_category_scores($attempt_id, $quiz_id) {
        // Get attempt details to get the answers
        $attempt = $this->get_attempt_details($attempt_id);
        if (!$attempt) {
            return '';
        }

        // Parse user answers
        $user_answers_data = json_decode($attempt->answers, true);
        if (empty($user_answers_data)) {
            return '';
        }

        // Convert to the format expected by qb_calculate_category_scores
        $user_answers = array();
        foreach ($user_answers_data as $answer) {
            if (isset($answer['question_id']) && isset($answer['option_id'])) {
                $user_answers[$answer['question_id']] = $answer['option_id'];
            }
        }

        if (empty($user_answers)) {
            return '';
        }

        // Include the quiz-results.php file to access the qb_calculate_category_scores function
        require_once plugin_dir_path(__FILE__) . '../../templates/quiz-results.php';
        
        // Calculate category scores
        $category_scores = qb_calculate_category_scores($quiz_id, $user_answers);
        
        if (empty($category_scores)) {
            return '';
        }

        $output = '<div style="margin: 30px 0; padding: 20px; background: white; border-radius: 6px; border: 1px solid #ddd;">';
        $output .= '<h4 style="color: #333; margin-bottom: 15px; text-align: center;">Category Breakdown</h4>';
        
        foreach ($category_scores as $category_id => $category) {
            $cat_percentage = $category['total'] > 0 ? round(($category['score'] / $category['total']) * 100) : 0;
            
            $output .= '<div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid ' . esc_attr($category['color']) . ';">';
            $output .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
            $output .= '<span style="font-weight: bold; color: #333;">' . esc_html($category['name']) . '</span>';
            $output .= '<span style="font-weight: bold;">' . esc_html($category['score']) . '/' . esc_html($category['total']) . ' (' . esc_html($cat_percentage) . '%)</span>';
            $output .= '</div>';
            
            // Show individual question breakdown
            if (!empty($category['questions'])) {
                $output .= '<div style="font-size: 14px; color: #666; margin-top: 8px;">';
                foreach ($category['questions'] as $question_data) {
                    $q_percentage = $question_data['max_points'] > 0 ? round(($question_data['score'] / $question_data['max_points']) * 100) : 0;
                    $output .= '<div style="margin: 4px 0; padding: 6px 0; border-bottom: 1px solid #eee;">';
                    $output .= '<span style="display: inline-block; width: 70%;">' . esc_html($question_data['question']) . '</span>';
                    $output .= '<span style="float: right;">' . esc_html($question_data['score']) . '/' . esc_html($question_data['max_points']) . ' (' . esc_html($q_percentage) . '%)</span>';
                    $output .= '</div>';
                }
                $output .= '</div>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
}