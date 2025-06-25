<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../includes/db/class-quiz-settings-db.php';

/**
 * Display and handle the quiz settings page
 * This page allows users to configure settings for each quiz
 */
function qb_quiz_settings_page() {
    global $wpdb;
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $settings_db = new QB_Quiz_Settings_DB();

    // Handle form submission
    if (isset($_POST['qb_save_settings'])) {
        check_admin_referer('qb_save_settings');
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        $settings = array(
            'is_paginated' => isset($_POST['is_paginated']) ? 1 : 0,
            'questions_per_page' => isset($_POST['questions_per_page']) ? max(1, intval($_POST['questions_per_page'])) : 1,
            'show_user_answers' => isset($_POST['show_user_answers']) ? 1 : 0,
            'allow_pdf_export' => isset($_POST['allow_pdf_export']) ? 1 : 0,
            'randomize_questions' => isset($_POST['randomize_questions']) ? 1 : 0,
            'randomize_answers' => isset($_POST['randomize_answers']) ? 1 : 0,
            'show_category_scores' => isset($_POST['show_category_scores']) ? 1 : 0
        );
        
        $result = $settings_db->save_settings($quiz_id, $settings);

        if ($result !== false) {
            add_settings_error(
                'qb_settings',
                'settings_updated',
                'Settings saved successfully!',
                'updated'
            );
        } else {
            add_settings_error(
                'qb_settings',
                'settings_error',
                'Error saving settings. Please try again.',
                'error'
            );
        }
    }

    // Get all quizzes
    // PCP: Direct DB query is used here to fetch all quizzes for the settings page (admin only, not performance-critical).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set internally and safe in this context.
    $quizzes = $wpdb->get_results("SELECT * FROM $quizzes_table ORDER BY title ASC");?>
    <div class="wrap">
        <h1>Quiz Settings</h1>
        
        <?php settings_errors('qb_settings'); ?>

        <?php if ($quizzes): ?>
            <form method="post" class="qb-settings-form">
                <?php wp_nonce_field('qb_save_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="quiz_id">Select Quiz</label></th>
                        <td>
                            <select name="quiz_id" id="quiz_id" required>
                                <option value="">-- Select a Quiz --</option>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <option value="<?php echo esc_attr($quiz->id); ?>">
                                        <?php echo esc_html($quiz->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="is_paginated">Enable Pagination</label></th>
                        <td>
                            <input type="checkbox" name="is_paginated" id="is_paginated" value="1">
                            <p class="description">Enable this to show questions one at a time with navigation buttons.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="questions_per_page">Questions Per Page</label></th>
                        <td>
                            <input type="number" name="questions_per_page" id="questions_per_page" value="1" min="1" required>
                            <p class="description">Number of questions to show on each page when pagination is enabled.</p>
                        </td>
                    </tr>                    
                    <tr>
                        <th><label for="show_user_answers">Show User Answers</label></th>
                        <td>
                            <input type="checkbox" name="show_user_answers" id="show_user_answers" value="1">
                            <p class="description">Show detailed results including user's answers and correct answers after quiz completion.</p>
                        </td>
                    </tr>                    
                    <tr>
                        <th><label for="allow_pdf_export">Allow PDF Export</label></th>
                        <td>
                            <input type="checkbox" name="allow_pdf_export" id="allow_pdf_export" value="1">
                            <p class="description">Allow users to download their quiz results as a PDF file.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="randomize_questions">Randomize Questions</label></th>
                        <td>
                            <input type="checkbox" name="randomize_questions" id="randomize_questions" value="1">
                            <p class="description">Randomize the order of questions each time the quiz is taken.</p>
                        </td>
                    </tr>                      <tr>
                        <th><label for="randomize_answers">Randomize Answer Options</label></th>
                        <td>
                            <input type="checkbox" name="randomize_answers" id="randomize_answers" value="1">
                            <p class="description">Randomize the order of answer options for each question.</p>
                        </td>
                    </tr>
                    
                    <?php
                    // Show premium feature - Sub-score Calculations
                    if (function_exists('qb_lock_feature')) {
                        if (qb_lock_feature('advanced_analytics', 'Sub-score calculations require a premium license to unlock advanced analytics features.')) {
                            // Feature is unlocked, show the setting
                            ?>
                            <tr>
                                <th><label for="show_category_scores">Show Sub-score Calculations</label></th>
                                <td>
                                    <input type="checkbox" name="show_category_scores" id="show_category_scores" value="1">
                                    <p class="description">Display category-based scoring breakdown in quiz results. Shows individual scores for each question category used in the quiz.</p>
                                </td>
                            </tr>
                            <?php
                        }
                        // If feature is locked, qb_lock_feature() will display the premium notice
                    } else {
                        // Fallback for when license manager is not loaded
                        ?>
                        <tr>
                            <th><label for="show_category_scores">Show Sub-score Calculations</label></th>
                            <td>
                                <input type="checkbox" name="show_category_scores" id="show_category_scores" value="1">
                                <p class="description">Display category-based scoring breakdown in quiz results. Shows individual scores for each question category used in the quiz.</p>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
                <?php submit_button('Save Settings', 'primary', 'qb_save_settings'); ?>
            </form>

            <script>            jQuery(document).ready(function($) {
                const quizSelect = document.getElementById('quiz_id');
                const isPaginated = document.getElementById('is_paginated');
                const questionsPerPage = document.getElementById('questions_per_page');
                const showUserAnswers = document.getElementById('show_user_answers');                const allowPdfExport = document.getElementById('allow_pdf_export');
                const randomizeQuestions = document.getElementById('randomize_questions');
                const randomizeAnswers = document.getElementById('randomize_answers');
                const showCategoryScores = document.getElementById('show_category_scores');

                // Load settings when quiz is selected
                quizSelect.addEventListener('change', function() {
                    if (this.value) {
                        loadQuizSettings(this.value);
                    } else {
                        resetToDefaults();
                    }
                });                function resetToDefaults() {
                    isPaginated.checked = false;
                    questionsPerPage.value = 1;
                    showUserAnswers.checked = false;
                    allowPdfExport.checked = false;
                    randomizeQuestions.checked = false;
                    randomizeAnswers.checked = false;
                    showCategoryScores.checked = false;
                }                function loadQuizSettings(quizId) {
                    $.post(ajaxurl, {
                        action: 'qb_get_quiz_settings',
                        quiz_id: quizId,
                        nonce: <?php echo wp_json_encode(wp_create_nonce('qb_get_settings')); ?>
                    })
                    .done(function(response) {
                        if (response.success && response.data) {                            const settings = response.data;
                            
                            isPaginated.checked = settings.is_paginated === '1' || settings.is_paginated === 1;
                            questionsPerPage.value = settings.questions_per_page || 1;
                            showUserAnswers.checked = settings.show_user_answers === '1' || settings.show_user_answers === 1;                            allowPdfExport.checked = settings.allow_pdf_export === '1' || settings.allow_pdf_export === 1;
                            randomizeQuestions.checked = settings.randomize_questions === '1' || settings.randomize_questions === 1;
                            randomizeAnswers.checked = settings.randomize_answers === '1' || settings.randomize_answers === 1;
                            showCategoryScores.checked = settings.show_category_scores === '1' || settings.show_category_scores === 1;
                        } else {
                            resetToDefaults();
                        }
                    })
                    .fail(function() {
                        resetToDefaults();
                    });
                }

                // Load settings if quiz is pre-selected
                if (quizSelect.value) {
                    loadQuizSettings(quizSelect.value);
                }

                // Preserve selected quiz after form submission
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('settings-updated')) {
                    const savedQuizId = localStorage.getItem('lastSelectedQuiz');
                    if (savedQuizId) {
                        quizSelect.value = savedQuizId;
                        loadQuizSettings(savedQuizId);
                    }
                }

                // Save selected quiz before form submission
                $('form').on('submit', function() {
                    localStorage.setItem('lastSelectedQuiz', quizSelect.value);
                });
            });
            </script>
        <?php else: ?>            <div class="notice notice-warning">
                <p>No quizzes found. Please <a href="<?php echo esc_url(admin_url('admin.php?page=quiz-builder')); ?>">create a quiz</a> first.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * AJAX handler for getting quiz settings
 */
function qb_get_quiz_settings_ajax() {
    check_ajax_referer('qb_get_settings', 'nonce');
    
    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    $settings_db = new QB_Quiz_Settings_DB();
    $settings = $settings_db->get_settings($quiz_id);    if ($settings) {
        // Ensure boolean values are properly handled
        $settings->is_paginated = (int)$settings->is_paginated;
        $settings->questions_per_page = (int)$settings->questions_per_page;
        $settings->show_user_answers = (int)$settings->show_user_answers;
        $settings->allow_pdf_export = (int)$settings->allow_pdf_export;
        $settings->randomize_questions = (int)$settings->randomize_questions;
        $settings->randomize_answers = (int)$settings->randomize_answers;
        wp_send_json_success($settings);
    } else {
        // Return default settings
        wp_send_json_success(array(
            'is_paginated' => 0,
            'questions_per_page' => 1,
            'show_user_answers' => 0,
            'allow_pdf_export' => 0,
            'randomize_questions' => 0,
            'randomize_answers' => 0
        ));
    }
}
add_action('wp_ajax_qb_get_quiz_settings', 'qb_get_quiz_settings_ajax');