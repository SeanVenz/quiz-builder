<?php
if (!defined('ABSPATH')) exit;

/**
 * Display and handle the quiz settings page
 * This page allows users to configure settings for each quiz
 */
function qb_quiz_settings_page() {
    global $wpdb;
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $settings_table = $wpdb->prefix . 'qb_quiz_settings';

    // Handle form submission
    if (isset($_POST['qb_save_settings'])) {
        handle_settings_save($settings_table);
    }

    // Get all quizzes
    $quizzes = $wpdb->get_results("SELECT * FROM $quizzes_table ORDER BY title ASC");
    ?>
    <div class="wrap">
        <h1>Quiz Settings</h1>

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
                </table>
                <?php submit_button('Save Settings', 'primary', 'qb_save_settings'); ?>
            </form>

            <script>
            jQuery(document).ready(function($) {
                const quizSelect = document.getElementById('quiz_id');
                const isPaginated = document.getElementById('is_paginated');
                const questionsPerPage = document.getElementById('questions_per_page');

                // Load settings when quiz is selected
                quizSelect.addEventListener('change', function() {
                    if (this.value) {
                        loadQuizSettings(this.value);
                    }
                });

                function loadQuizSettings(quizId) {
                    $.post(ajaxurl, {
                        action: 'qb_get_quiz_settings',
                        quiz_id: quizId,
                        nonce: '<?php echo wp_create_nonce('qb_get_settings'); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            const settings = response.data;
                            isPaginated.checked = settings.is_paginated;
                            questionsPerPage.value = settings.questions_per_page;
                        } else {
                            // Reset to defaults
                            isPaginated.checked = false;
                            questionsPerPage.value = 1;
                        }
                    })
                    .fail(function() {
                        // Reset to defaults on error
                        isPaginated.checked = false;
                        questionsPerPage.value = 1;
                    });
                }
            });
            </script>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>No quizzes found. Please <a href="<?php echo admin_url('admin.php?page=quiz-builder'); ?>">create a quiz</a> first.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Handle saving quiz settings
 * 
 * @param string $settings_table The name of the settings table
 */
function handle_settings_save($settings_table) {
    check_admin_referer('qb_save_settings');
    global $wpdb;
    
    $quiz_id = intval($_POST['quiz_id']);
    $is_paginated = isset($_POST['is_paginated']) ? 1 : 0;
    $questions_per_page = max(1, intval($_POST['questions_per_page']));
    
    // Check if settings exist for this quiz
    $existing_settings = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $settings_table WHERE quiz_id = %d",
        $quiz_id
    ));

    if ($existing_settings) {
        // Update existing settings
        $wpdb->update(
            $settings_table,
            array(
                'is_paginated' => $is_paginated,
                'questions_per_page' => $questions_per_page
            ),
            array('quiz_id' => $quiz_id)
        );
    } else {
        // Insert new settings
        $wpdb->insert(
            $settings_table,
            array(
                'quiz_id' => $quiz_id,
                'is_paginated' => $is_paginated,
                'questions_per_page' => $questions_per_page
            )
        );
    }

    add_settings_error(
        'qb_settings',
        'settings_updated',
        'Settings saved successfully!',
        'updated'
    );
}

/**
 * AJAX handler for getting quiz settings
 */
function qb_get_quiz_settings_ajax() {
    check_ajax_referer('qb_get_settings', 'nonce');
    
    global $wpdb;
    $quiz_id = intval($_POST['quiz_id']);
    $settings_table = $wpdb->prefix . 'qb_quiz_settings';

    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT is_paginated, questions_per_page FROM $settings_table WHERE quiz_id = %d",
        $quiz_id
    ));

    if ($settings) {
        wp_send_json_success($settings);
    } else {
        wp_send_json_error(['message' => 'No settings found']);
    }
}
add_action('wp_ajax_qb_get_quiz_settings', 'qb_get_quiz_settings_ajax'); 