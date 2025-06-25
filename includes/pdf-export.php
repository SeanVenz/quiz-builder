<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PDF Export functionality
 */

// Add AJAX handler for PDF export
add_action('wp_ajax_qb_export_pdf', 'qb_export_pdf');
add_action('wp_ajax_nopriv_qb_export_pdf', 'qb_export_pdf');

function qb_export_pdf() {
    $attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    
    // Verify nonce
    if (!wp_verify_nonce($nonce, 'qb_pdf_export_' . $attempt_id)) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    
    // Get attempt details
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $attempts_table WHERE id = %d", $attempt_id));
    if (!$attempt) {
        wp_die('Quiz attempt not found');
    }
    
    // Get quiz details
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}qb_quizzes WHERE id = %d", $attempt->quiz_id));
    if (!$quiz) {
        wp_die('Quiz not found');
    }
    
    // Check if PDF export is enabled for this quiz
    require_once QB_PATH . 'includes/db/class-quiz-settings-db.php';
    $settings_db = new QB_Quiz_Settings_DB();
    $settings = $settings_db->get_settings($attempt->quiz_id);
    
    if (!$settings || !$settings->allow_pdf_export) {
        wp_die('PDF export is not enabled for this quiz');
    }
    
    // Generate PDF
    qb_generate_pdf($attempt, $quiz);
}

/**
 * Generate PDF from quiz results
 */
function qb_generate_pdf($attempt, $quiz) {
    // Get quiz results data
    require_once QB_PATH . 'includes/results/class-quiz-results-display.php';
    $results_display = new QB_Quiz_Results_Display();
    $answers = $results_display->get_attempt_answers($attempt->id);
    
    $percentage = round(($attempt->score / $attempt->total_points) * 100);
    $current_date = current_time('Y-m-d H:i:s');
    
    // Create PDF content using HTML template
    $html_content = qb_generate_pdf_html($quiz, $attempt, $answers, $percentage, $current_date);
    
    // Use the PDF manager for proper PDF generation
    require_once QB_PATH . 'includes/class-pdf-manager.php';
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $quiz->title) . '_results_' . gmdate('Y-m-d_H-i-s') . '.pdf';
    
    QB_PDF_Manager::generate_pdf($html_content, $filename, $quiz->title . ' - Quiz Results');
}

/**
 * Generate HTML content for PDF using template
 */
function qb_generate_pdf_html($quiz, $attempt, $answers, $percentage, $current_date) {
    ob_start();
    include QB_PATH . 'templates/pdf-template.php';
    return ob_get_clean();
}
