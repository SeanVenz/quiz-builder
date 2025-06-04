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
        );        // Only show detailed results if enabled in settings
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
    }

    /**
     * Get attempt details from database
     */
    private function get_attempt_details($attempt_id) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}qb_attempts WHERE id = %d",
            $attempt_id
        );
        error_log('QB Debug: Attempt query: ' . $query);
        $result = $this->wpdb->get_row($query);
        error_log('QB Debug: Attempt result: ' . print_r($result, true));
        return $result;
    }

    /**
     * Get quiz details
     */
    private function get_quiz_details($quiz_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}qb_quizzes 
            WHERE id = %d",
            $quiz_id
        ));
    }    /**
     * Get attempt answers with question and option details
     */
    public function get_attempt_answers($attempt_id) {
        error_log('QB Debug: Starting get_attempt_answers with ID: ' . $attempt_id);
        
        $attempt = $this->get_attempt_details($attempt_id);
        if (!$attempt) {
            error_log('QB Debug: No attempt found');
            return array();
        }

        error_log('QB Debug: Raw answers data: ' . print_r($attempt->answers, true));
        
        $answers_data = json_decode($attempt->answers, true);
        if (empty($answers_data)) {
            error_log('QB Debug: Failed to decode answers JSON or empty data');
            return array();
        }

        error_log('QB Debug: Decoded answers data: ' . print_r($answers_data, true));

        // Get all question IDs from the answers
        $question_ids = array();
        foreach ($answers_data as $data) {
            if (isset($data['question_id'])) {
                $question_ids[] = $data['question_id'];
            }
        }
        
        error_log('QB Debug: Extracted question IDs: ' . print_r($question_ids, true));

        if (empty($question_ids)) {
            error_log('QB Debug: No question IDs found');
            return array();
        }

        // Create placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        
        // Get questions with their correct options (highest points option is correct)
        $query = $this->wpdb->prepare(
            "SELECT q.id, q.question, 
                    MAX(o.points) as max_points,
                    o2.id as correct_option,
                    o2.option_text as correct_text
            FROM {$this->wpdb->prefix}qb_questions q
            LEFT JOIN {$this->wpdb->prefix}qb_options o ON q.id = o.question_id
            LEFT JOIN {$this->wpdb->prefix}qb_options o2 ON q.id = o2.question_id AND o.points = o2.points
            WHERE q.id IN ($placeholders)
            GROUP BY q.id, q.question
            ORDER BY q.order ASC",
            $question_ids
        );

        error_log('QB Debug: Question query: ' . $query);
        
        $questions = $this->wpdb->get_results($query);
        
        error_log('QB Debug: Questions found: ' . print_r($questions, true));

        if (!$questions) {
            error_log('QB Debug: No questions found in database');
            return array();
        }

        // Get all selected option IDs
        $selected_option_ids = array();
        foreach ($answers_data as $data) {
            if (isset($data['option_id'])) {
                $selected_option_ids[] = $data['option_id'];
            }
        }

        error_log('QB Debug: Selected option IDs: ' . print_r($selected_option_ids, true));

        // Get all selected options in one query
        $selected_options = array();
        if (!empty($selected_option_ids)) {
            $option_placeholders = implode(',', array_fill(0, count($selected_option_ids), '%d'));
            $options_query = $this->wpdb->prepare(
                "SELECT id, option_text 
                FROM {$this->wpdb->prefix}qb_options 
                WHERE id IN ($option_placeholders)",
                $selected_option_ids
            );
            
            error_log('QB Debug: Options query: ' . $options_query);
            
            $options = $this->wpdb->get_results($options_query);
            
            error_log('QB Debug: Options found: ' . print_r($options, true));
            
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

        error_log('QB Debug: Final results: ' . print_r($results, true));

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
            esc_url($export_url)
        );
    }
}