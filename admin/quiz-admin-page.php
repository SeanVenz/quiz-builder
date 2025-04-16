<?php
if (!defined('ABSPATH'))
    exit;

function qb_add_admin_menu()
{
    add_menu_page(
        'Quiz Builder',
        'Quiz Builder',
        'manage_options',
        'quiz-builder',
        'qb_admin_page_content',
        'dashicons-welcome-learn-more',
        6
    );
    add_submenu_page(
        null, // hidden from main menu
        'Manage Questions', // page title
        'Manage Questions', // menu title (won't show since it's null)
        'manage_options',
        'qb-manage-questions',
        'qb_manage_questions_page'
    );
    add_submenu_page(
        'quiz-builder',
        'Quiz Attempts',
        'Quiz Attempts',
        'manage_options',
        'qb-quiz-attempts',
        'qb_quiz_attempts_page'
    );
    add_submenu_page(
        'quiz-builder',
        'Quiz Settings',
        'Settings',
        'manage_options',
        'qb-quiz-settings',
        'qb_quiz_settings_page'
    );
}

function qb_admin_page_content()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_quizzes';

    // Handle form submission
    if (isset($_POST['qb_add_quiz'])) {
        $title = sanitize_text_field($_POST['quiz_title']);
        $description = sanitize_textarea_field($_POST['quiz_description']);

        if (!empty($title)) {
            $wpdb->insert($table_name, [
                'title' => $title,
                'description' => $description,
            ]);
            echo '<div class="updated notice"><p>Quiz added successfully!</p></div>';
        } else {
            echo '<div class="error notice"><p>Please enter a quiz title.</p></div>';
        }
    }

    $quizzes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    ?>
    <div class="wrap">
        <h1>Quiz Builder</h1>

        <h2>Add New Quiz</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="quiz_title">Quiz Title</label></th>
                    <td><input name="quiz_title" type="text" id="quiz_title" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="quiz_description">Description</label></th>
                    <td><textarea name="quiz_description" id="quiz_description" rows="4" class="large-text"></textarea></td>
                </tr>
            </table>
            <?php submit_button('Add Quiz', 'primary', 'qb_add_quiz'); ?>
        </form>

        <hr>

        <h2>All Quizzes</h2>
        <?php if ($quizzes): ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Quiz Title</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $quiz): ?>
                        <tr>
                            <td><?php echo esc_html($quiz->id); ?></td>
                            <td><?php echo esc_html($quiz->title); ?></td>
                            <td><?php echo esc_html($quiz->description); ?></td>
                            <td><?php echo esc_html($quiz->created_at); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=qb-manage-questions&quiz_id=' . $quiz->id); ?>">Manage
                                    Questions</a>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No quizzes found.</p>
        <?php endif; ?>
    </div>
    <?php
}

function qb_quiz_attempts_page() {
    global $wpdb;
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $users_table = $wpdb->users;

    $attempts = $wpdb->get_results("
        SELECT a.*, q.title as quiz_title, u.display_name as user_name 
        FROM $attempts_table a
        LEFT JOIN $quizzes_table q ON a.quiz_id = q.id
        LEFT JOIN $users_table u ON a.user_id = u.ID
        ORDER BY a.created_at DESC
    ");
    ?>
    <div class="wrap">
        <h1>Quiz Attempts</h1>
        
        <?php if ($attempts): ?>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=qb_export_attempts_csv'), 'qb_export_csv', 'nonce'); ?>" class="button">Export to CSV</a>
                </div>
                <br class="clear">
            </div>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Attempt ID</th>
                        <th>Quiz</th>
                        <th>User</th>
                        <th>Score</th>
                        <th>Total Points</th>
                        <th>Percentage</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td><?php echo esc_html($attempt->random_id); ?></td>
                            <td><?php echo esc_html($attempt->quiz_title); ?></td>
                            <td><?php echo esc_html($attempt->user_name ?: 'Guest'); ?></td>
                            <td><?php echo esc_html($attempt->score); ?></td>
                            <td><?php echo esc_html($attempt->total_points); ?></td>
                            <td><?php echo esc_html(round(($attempt->score / $attempt->total_points) * 100)) . '%'; ?></td>
                            <td><?php echo esc_html($attempt->created_at); ?></td>
                            <td>
                                <a href="#" class="view-attempt-details" data-attempt-id="<?php echo esc_attr($attempt->random_id); ?>">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="attempt-details-modal" style="display: none;">
                <div class="attempt-details-content"></div>
            </div>

            <style>
                #attempt-details-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 9999;
                }
                .attempt-details-content {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: white;
                    padding: 20px;
                    border-radius: 5px;
                    max-width: 80%;
                    max-height: 80%;
                    overflow-y: auto;
                }
                .attempt-details table {
                    margin-top: 15px;
                }
                .attempt-details .attempt-info {
                    background: #f9f9f9;
                    padding: 15px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.view-attempt-details').forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const attemptId = this.dataset.attemptId;
                            const modal = document.getElementById('attempt-details-modal');
                            const content = modal.querySelector('.attempt-details-content');
                            
                            // Show loading state
                            content.innerHTML = '<p>Loading...</p>';
                            modal.style.display = 'block';
                            
                            // Fetch attempt details via AJAX
                            fetch(ajaxurl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    action: 'qb_get_attempt_details',
                                    attempt_id: attemptId,
                                    nonce: '<?php echo wp_create_nonce('qb_attempt_details'); ?>'
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    content.innerHTML = data.data.html;
                                } else {
                                    content.innerHTML = '<p class="error">Error loading attempt details.</p>';
                                }
                            })
                            .catch(error => {
                                content.innerHTML = '<p class="error">Error loading attempt details.</p>';
                                console.error('Error:', error);
                            });
                        });
                    });

                    // Close modal when clicking outside
                    document.getElementById('attempt-details-modal').addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.style.display = 'none';
                        }
                    });
                });
            </script>
        <?php else: ?>
            <p>No quiz attempts found.</p>
        <?php endif; ?>
    </div>
    <?php
}

function qb_quiz_settings_page() {
    global $wpdb;
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $settings_table = $wpdb->prefix . 'qb_quiz_settings';

    // Handle form submission
    if (isset($_POST['qb_save_settings'])) {
        check_admin_referer('qb_save_settings');
        
        $quiz_id = intval($_POST['quiz_id']);
        $is_paginated = isset($_POST['is_paginated']) ? 1 : 0;
        $questions_per_page = intval($_POST['questions_per_page']);
        
        if ($questions_per_page < 1) {
            $questions_per_page = 1;
        }

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

        echo '<div class="updated notice"><p>Settings saved successfully!</p></div>';
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
                document.addEventListener('DOMContentLoaded', function() {
                    const quizSelect = document.getElementById('quiz_id');
                    const isPaginated = document.getElementById('is_paginated');
                    const questionsPerPage = document.getElementById('questions_per_page');

                    // Load settings when quiz is selected
                    quizSelect.addEventListener('change', function() {
                        if (this.value) {
                            fetch(ajaxurl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    action: 'qb_get_quiz_settings',
                                    quiz_id: this.value,
                                    nonce: '<?php echo wp_create_nonce('qb_get_settings'); ?>'
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const settings = data.data;
                                    isPaginated.checked = settings.is_paginated;
                                    questionsPerPage.value = settings.questions_per_page;
                                } else {
                                    // Reset to defaults
                                    isPaginated.checked = false;
                                    questionsPerPage.value = 1;
                                }
                            });
                        }
                    });
                });
            </script>
        <?php else: ?>
            <p>No quizzes found. Please create a quiz first.</p>
        <?php endif; ?>
    </div>
    <?php
}
