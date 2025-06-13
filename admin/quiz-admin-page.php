<?php
if (!defined('ABSPATH'))
    exit;

// Add admin menu
add_action('admin_menu', 'qb_add_admin_menu');
function qb_add_admin_menu()
{
    // Main menu page - Dashboard
    add_menu_page(
        'Quiz Builder',
        'Quiz Builder',
        'manage_options',
        'quiz-builder',
        'qb_dashboard_page',        'dashicons-welcome-learn-more',
        6
    );
    
    // Submenu pages
    add_submenu_page(
        'quiz-builder',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'quiz-builder',
        'qb_dashboard_page'
    );

    // Getting Started - always available
    add_submenu_page(
        'quiz-builder',
        'Getting Started',
        'Getting Started',
        'manage_options',
        'qb-getting-started',
        'qb_getting_started_page'
    );

    add_submenu_page(
        'quiz-builder',
        'Manage Quiz',
        'Manage Quiz',
        'manage_options',
        'qb-manage-quiz',
        'qb_manage_questions_page'
    );

    add_submenu_page(
        'quiz-builder',
        'Quiz Settings',
        'Settings',
        'manage_options',
        'qb-quiz-settings',
        'qb_quiz_settings_page'
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
        'Categories',
        'Categories',
        'manage_options',
        'qb-categories',
        'qb_categories_page'
    );
}

function qb_admin_page_content()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_quizzes';    // Handle form submission
    if (isset($_POST['qb_add_quiz'])) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['qb_add_quiz_nonce'], 'qb_add_quiz_action')) {
            wp_die('Security check failed');
        }
        
        $title = isset($_POST['quiz_title']) ? sanitize_text_field(wp_unslash($_POST['quiz_title'])) : '';
        $description = isset($_POST['quiz_description']) ? sanitize_textarea_field(wp_unslash($_POST['quiz_description'])) : '';

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

    $quizzes = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY created_at DESC", $table_name));

    ?>
    <div class="wrap">
        <h1>Quiz Builder</h1>        <h2>Add New Quiz</h2>
        <form method="post">
            <?php wp_nonce_field('qb_add_quiz_action', 'qb_add_quiz_nonce'); ?>
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
                                <a href="<?php echo esc_url(admin_url('admin.php?page=qb-manage-questions&quiz_id=' . $quiz->id)); ?>">Manage
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
    $users_table = $wpdb->users;    $attempts = $wpdb->get_results($wpdb->prepare("
        SELECT a.*, q.title as quiz_title, u.display_name as user_name 
        FROM %i a
        LEFT JOIN %i q ON a.quiz_id = q.id
        LEFT JOIN %i u ON a.user_id = u.ID
        ORDER BY a.created_at DESC
    ", $attempts_table, $quizzes_table, $users_table));
    ?>
    <div class="wrap">
        <h1>Quiz Attempts</h1>
        
        <?php if ($attempts): ?>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <!-- <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=qb_export_attempts_csv'), 'qb_export_csv', 'nonce')); ?>" class="button">Export to CSV</a> -->
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
                                },                                body: new URLSearchParams({
                                    action: 'qb_get_attempt_details',
                                    attempt_id: attemptId,
                                    nonce: <?php echo wp_json_encode(wp_create_nonce('qb_attempt_details')); ?>
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

function qb_dashboard_page() {
    global $wpdb;
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    
    // Check onboarding completion status
    $onboarding_completed = get_option('qb_onboarding_completed', false);
    $quiz_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $quizzes_table));
    
    // Always show dashboard if onboarding is completed, regardless of quiz count
    // This allows users to return to dashboard after completing onboarding
    if ($onboarding_completed || $quiz_count > 0) {
        // Show dashboard for users who have completed onboarding or have existing quizzes
        qb_show_dashboard();
    } else {
        // Show onboarding only for fresh installations with no quizzes and incomplete onboarding
        qb_show_onboarding();
    }
}

function qb_show_onboarding() {
    // Get plugin URL
    $plugin_url = plugins_url('', dirname(__FILE__));
    
    // Enqueue our separated CSS and JavaScript files
    wp_enqueue_style('qb-onboarding-css', $plugin_url . '/assets/css/onboarding.css', array(), '1.0.0');
    wp_enqueue_script('qb-onboarding-js', $plugin_url . '/assets/js/onboarding.js', array('jquery'), '1.0.0', true);
      // Localize script for AJAX
    wp_localize_script('qb-onboarding-js', 'qb_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'admin_url' => admin_url(),
        'nonce' => wp_create_nonce('qb_onboarding_quiz')
    ));
    
    // Include the HTML template
    include_once(plugin_dir_path(dirname(__FILE__)) . 'templates/onboarding.php');
}

function qb_show_dashboard() {
    global $wpdb;
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $attempts_table = $wpdb->prefix . 'qb_attempts';
      // Get statistics
    $total_quizzes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $quizzes_table));
    $total_questions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $questions_table));
    $total_attempts = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $attempts_table));
    $recent_attempts = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $attempts_table));
      // Get recent quizzes
    $recent_quizzes = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY created_at DESC LIMIT 5", $quizzes_table));
    
    ?>
    <div class="wrap">
        <h1>Quiz Builder Dashboard</h1>
        
        <!-- Statistics Cards -->        <div class="qb-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div class="qb-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h3 style="margin: 0 0 10px 0; color: #666;">Total Quizzes</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 0; color: #2271b1;"><?php echo esc_html($total_quizzes); ?></p>
            </div>
            <div class="qb-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h3 style="margin: 0 0 10px 0; color: #666;">Total Questions</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a32a;"><?php echo esc_html($total_questions); ?></p>
            </div>
            <div class="qb-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h3 style="margin: 0 0 10px 0; color: #666;">Total Attempts</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 0; color: #d63638;"><?php echo esc_html($total_attempts); ?></p>
            </div>
            <div class="qb-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h3 style="margin: 0 0 10px 0; color: #666;">Recent Attempts (7 days)</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 0; color: #f56e00;"><?php echo esc_html($recent_attempts); ?></p>
            </div>
        </div>
        
        <!-- Quick Actions -->        <div class="qb-quick-actions" style="margin: 30px 0;">
            <h2>Quick Actions</h2>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=qb-manage-quiz')); ?>" class="button button-primary">Create New Quiz</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=qb-quiz-attempts')); ?>" class="button">View All Attempts</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=qb-quiz-settings')); ?>" class="button">Quiz Settings</a>
            </p>
        </div>
        
        <!-- Recent Quizzes -->
        <div class="qb-recent-quizzes">
            <h2>Recent Quizzes</h2>
            <?php if ($recent_quizzes): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Questions</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_quizzes as $quiz): ?>
                            <?php
                            $question_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE quiz_id = %d", $questions_table, $quiz->id));
                            ?>
                            <tr>                                <td><strong><?php echo esc_html($quiz->title); ?></strong></td>
                                <td><?php echo esc_html(wp_trim_words($quiz->description, 10)); ?></td>
                                <td><?php echo esc_html($question_count); ?></td>
                                <td><?php echo esc_html(gmdate('M j, Y', strtotime($quiz->created_at))); ?></td><td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=qb-manage-quiz&quiz_id=' . $quiz->id)); ?>" class="button button-small">Manage</a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=qb-quiz-settings&quiz_id=' . $quiz->id)); ?>" class="button button-small">Settings</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No quizzes created yet. <a href="<?php echo esc_url(admin_url('admin.php?page=qb-manage-quiz')); ?>">Create your first quiz</a>!</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
