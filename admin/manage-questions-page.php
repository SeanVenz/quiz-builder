<?php
if (!defined('ABSPATH')) exit;

// Add the action handlers for quiz management
add_action('admin_post_add_quiz', 'handle_add_quiz_submission');
add_action('admin_post_edit_quiz', 'handle_edit_quiz_submission');
add_action('admin_post_delete_quiz', 'handle_delete_quiz_submission');

function handle_add_quiz_submission() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('qb_add_quiz');
    
    global $wpdb;
    $title = isset($_POST['quiz_title']) ? sanitize_text_field(wp_unslash($_POST['quiz_title'])) : '';
    $description = isset($_POST['quiz_description']) ? sanitize_textarea_field(wp_unslash($_POST['quiz_description'])) : '';
    
    if (!empty($title)) {
        // PCP: Direct DB insert for quiz creation (admin action, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($wpdb->prefix . 'qb_quizzes', [
            'title' => $title,
            'description' => $description,
            'created_at' => current_time('mysql')
        ]);

        if ($result) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'quiz-builder',
                    'quiz_id' => $wpdb->insert_id,
                    'message' => 'quiz_added'
                ),
                admin_url('admin.php')
            );
        } else {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'quiz-builder',
                    'message' => 'quiz_error'
                ),
                admin_url('admin.php')
            );
        }
    } else {
        $redirect_url = add_query_arg(
            array(
                'page' => 'quiz-builder',
                'message' => 'quiz_error'
            ),
            admin_url('admin.php')
        );
    }
    
    wp_safe_redirect($redirect_url);
    exit;
}

function handle_edit_quiz_submission() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('qb_edit_quiz');
    
    global $wpdb;
    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    $title = isset($_POST['quiz_title']) ? sanitize_text_field(wp_unslash($_POST['quiz_title'])) : '';
    $description = isset($_POST['quiz_description']) ? sanitize_textarea_field(wp_unslash($_POST['quiz_description'])) : '';
    
    if (!empty($title)) {
        // PCP: Direct DB update for quiz editing (admin action, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $wpdb->prefix . 'qb_quizzes',
            [
                'title' => $title,
                'description' => $description
            ],
            ['id' => $quiz_id]
        );

        $message = $result !== false ? 'quiz_updated' : 'quiz_error';
    } else {
        $message = 'quiz_error';
    }
    
    wp_safe_redirect(add_query_arg(
        array(
            'page' => 'quiz-builder',
            'message' => $message
        ),
        admin_url('admin.php')
    ));
    exit;
}

function handle_delete_quiz_submission() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('qb_delete_quiz');
    
    global $wpdb;
    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    
    // Delete related records first
    // PCP: Direct DB delete for related options (admin action, no caching needed).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($wpdb->prefix . 'qb_options', ['question_id' => 
        $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}qb_questions WHERE quiz_id = %d",
            $quiz_id
        ))
    ]);
    // PCP: Direct DB delete for questions (admin action, no caching needed).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($wpdb->prefix . 'qb_questions', ['quiz_id' => $quiz_id]);
    // PCP: Direct DB delete for attempts (admin action, no caching needed).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($wpdb->prefix . 'qb_attempts', ['quiz_id' => $quiz_id]);
    
    // Delete the quiz
    // PCP: Direct DB delete for quiz (admin action, no caching needed).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $result = $wpdb->delete($wpdb->prefix . 'qb_quizzes', ['id' => $quiz_id]);
    
    wp_safe_redirect(add_query_arg(
        array(
            'page' => 'quiz-builder',
            'message' => $result !== false ? 'quiz_deleted' : 'quiz_error'
        ),
        admin_url('admin.php')
    ));
    exit;
}

function qb_manage_questions_page() {
    global $wpdb;    // Add required scripts and styles
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('jquery-ui', plugins_url('assets/css/jquery-ui.css', dirname(__FILE__, 2) . '/quiz-builder.php'), array(), '1.12.1');
    wp_enqueue_style('qb-admin-styles', plugins_url('assets/css/admin-styles.css', dirname(__FILE__, 2) . '/quiz-builder.php'), array(), '1.0.0');

    $quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

    // Show messages
    if (isset($_GET['message'])) {
        switch ($_GET['message']) {
            case 'quiz_added':
                echo '<div class="notice notice-success is-dismissible"><p>Quiz added successfully!</p></div>';
                break;
            case 'quiz_updated':
                echo '<div class="notice notice-success is-dismissible"><p>Quiz updated successfully!</p></div>';
                break;
            case 'quiz_deleted':
                echo '<div class="notice notice-success is-dismissible"><p>Quiz deleted successfully!</p></div>';
                break;
            case 'quiz_error':
                echo '<div class="notice notice-error is-dismissible"><p>Error processing quiz. Please try again.</p></div>';
                break;
        }
    }

    // If no quiz ID is provided, show the quiz list
    if (!$quiz_id) {
        // Get all quizzes
        // PCP: Direct DB select for admin quiz list (not performance-critical, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $quizzes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}qb_quizzes ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1>Quiz Builder</h1>
            
            <h2>Add New Quiz</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="qb-form">
                <?php wp_nonce_field('qb_add_quiz'); ?>
                <input type="hidden" name="action" value="add_quiz">
                <table class="form-table">
                    <tr>
                        <th><label for="quiz_title">Quiz Title</label></th>
                        <td>
                            <input name="quiz_title" type="text" id="quiz_title" class="regular-text" required>
                            <p class="description">Enter the title of your quiz</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="quiz_description">Description</label></th>
                        <td>
                            <textarea name="quiz_description" id="quiz_description" rows="4" class="large-text"></textarea>
                            <p class="description">Enter a description for your quiz (optional)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Add Quiz', 'primary', 'submit', false); ?>
            </form>

            <hr>

            <h2>Your Quizzes</h2>
            <?php if ($quizzes): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $quiz): ?>
                            <tr>
                                <td><?php echo esc_html($quiz->id); ?></td>
                                <td>
                                    <span class="quiz-title"><?php echo esc_html($quiz->title); ?></span>
                                    <div class="edit-form" style="display: none;">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('qb_edit_quiz'); ?>
                                            <input type="hidden" name="action" value="edit_quiz">
                                            <input type="hidden" name="quiz_id" value="<?php echo esc_attr($quiz->id); ?>">
                                            <input type="text" name="quiz_title" value="<?php echo esc_attr($quiz->title); ?>" required>
                                            <textarea name="quiz_description"><?php echo esc_textarea($quiz->description); ?></textarea>
                                            <button type="submit" class="button button-primary">Save</button>
                                            <button type="button" class="button cancel-edit">Cancel</button>
                                        </form>
                                    </div>
                                </td>
                                <td><?php echo esc_html($quiz->description); ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'quiz-builder', 'quiz_id' => $quiz->id), admin_url('admin.php'))); ?>" class="button">Manage Questions</a>
                                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'qb-quiz-settings', 'quiz_id' => $quiz->id), admin_url('admin.php'))); ?>" class="button">Settings</a>
                                        <button class="button edit-quiz">Edit</button>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                            <?php wp_nonce_field('qb_delete_quiz'); ?>
                                            <input type="hidden" name="action" value="delete_quiz">
                                            <input type="hidden" name="quiz_id" value="<?php echo esc_attr($quiz->id); ?>">
                                            <button type="submit" class="button delete-quiz" onclick="return confirm('Are you sure you want to delete this quiz? This will also delete all questions, options, and attempts associated with it.');">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <style>
                    .edit-form {
                        margin-top: 10px;
                        padding: 10px;
                        background: #f9f9f9;
                        border: 1px solid #ddd;
                    }
                    .edit-form input[type="text"] {
                        width: 100%;
                        margin-bottom: 10px;
                    }
                    .edit-form textarea {
                        width: 100%;
                        height: 100px;
                        margin-bottom: 10px;
                    }
                    .row-actions {
                        display: flex;
                        gap: 5px;
                    }
                    .delete-quiz {
                        color: #dc3232;
                    }
                </style>

                <script>
                jQuery(document).ready(function($) {
                    // Edit quiz functionality
                    $('.edit-quiz').on('click', function() {
                        var $row = $(this).closest('tr');
                        $row.find('.quiz-title').hide();
                        $row.find('.edit-form').show();
                    });

                    // Cancel edit
                    $('.cancel-edit').on('click', function() {
                        var $row = $(this).closest('tr');
                        $row.find('.quiz-title').show();
                        $row.find('.edit-form').hide();
                    });
                });
                </script>
            <?php else: ?>
                <p>No quizzes found. Create your first quiz above!</p>
            <?php endif; ?>
        </div>
        <?php
        return;
    }
    
    // PCP: Direct DB insert for quiz creation (admin action, no caching needed).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}qb_quizzes WHERE id = %d", $quiz_id));

    if (!$quiz) {
        echo '<div class="error"><p>Invalid quiz ID.</p></div>';
        return;
    }

    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';

    // Handle form submissions
    if (isset($_POST['action'])) {
        check_admin_referer('qb_manage_questions');
        
        switch ($_POST['action']) {            case 'add_question':
                $question_text = isset($_POST['question_text']) ? sanitize_text_field(wp_unslash($_POST['question_text'])) : '';
                $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
                $required = isset($_POST['question_required']) ? 1 : 0;
                
                if (!empty($question_text)) {
                    // Get the next order number
                    // PCP: Direct DB select for next order (admin action, no caching needed).
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set internally and safe in this context.
                    $next_order = $wpdb->get_var($wpdb->prepare("SELECT MAX(`order`) + 1 FROM $questions_table WHERE quiz_id = %d", $quiz_id)) ?: 1;

                    $insert_data = [
                        'quiz_id' => $quiz_id,
                        'question' => $question_text,
                        'required' => $required,
                        'order' => $next_order
                    ];
                    
                    // Add category_id if provided
                    if ($category_id) {
                        $insert_data['category_id'] = $category_id;
                    }
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $result = $wpdb->insert($questions_table, $insert_data);
                    
                    if ($result) {
                        $success_message = 'Question added successfully!';
                        if ($required) {
                            $success_message .= ' (Required)';
                        }
                        if ($category_id) {
                            $success_message .= ' (Categorized)';
                        }
                        echo '<div class="updated notice"><p>' . esc_html($success_message) . '</p></div>';
                    } else {
                        echo '<div class="error notice"><p>Error adding question. Please try again.</p></div>';
                    }
                } else {
                    echo '<div class="error notice"><p>Please enter a question text.</p></div>';
                }
                break;            case 'edit_question':
                $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
                $question_text = isset($_POST['question_text']) ? sanitize_text_field(wp_unslash($_POST['question_text'])) : '';
                $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
                $required = isset($_POST['question_required']) ? 1 : 0;
                
                if (!empty($question_text)) {
                    $update_data = ['question' => $question_text];
                    
                    // Add category_id to update data (can be null to remove category)
                    $update_data['category_id'] = $category_id;
                    
                    // Add required status to update data
                    $update_data['required'] = $required;
                    
                    // PCP: Direct DB update for question editing (admin action, no caching needed).
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($questions_table, 
                        $update_data,
                        ['id' => $question_id]
                    );
                    echo '<div class="updated notice"><p>Question updated!</p></div>';
                }
                break;

            case 'delete_question':
                $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
                // Get the order of the question being deleted
                // PCP: Direct DB select for order of deleted question (admin action, no caching needed).
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set internally and safe in this context.
                $deleted_order = $wpdb->get_var($wpdb->prepare("SELECT `order` FROM $questions_table WHERE id = %d", $question_id));
                
                // Delete the question
                // PCP: Direct DB delete for question (admin action, no caching needed).
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete($questions_table, ['id' => $question_id]);
                
                // Update orders of remaining questions
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set internally and safe in this context.
                $wpdb->query($wpdb->prepare( "UPDATE $questions_table SET `order` = `order` - 1  WHERE quiz_id = %d AND `order` > %d", $quiz_id, $deleted_order));
                
                echo '<div class="updated notice"><p>Question deleted!</p></div>';
                break;

            case 'add_option':
                $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
                $option_text = isset($_POST['option_text']) ? sanitize_text_field(wp_unslash($_POST['option_text'])) : '';
                $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                if (!empty($option_text)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->insert($options_table, [
                        'question_id' => $question_id,
                        'option_text' => $option_text,
                        'points' => $points,
                    ]);
                    echo '<div class="updated notice"><p>Option added!</p></div>';
                }
                break;

            case 'edit_option':
                $option_id = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
                $option_text = isset($_POST['option_text']) ? sanitize_text_field(wp_unslash($_POST['option_text'])) : '';
                $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                if (!empty($option_text)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($options_table, 
                        ['option_text' => $option_text, 'points' => $points],
                        ['id' => $option_id]
                    );
                    echo '<div class="updated notice"><p>Option updated!</p></div>';
                }
                break;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            case 'delete_option':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $option_id = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete($options_table, ['id' => $option_id]);
                echo '<div class="updated notice"><p>Option deleted!</p></div>';
                break;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            case 'save_order':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $order = isset($_POST['order']) ? json_decode(stripslashes(wp_unslash($_POST['order']))) : array();
                // Sanitize order IDs
                $order = is_array($order) ? array_map('intval', $order) : array();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                foreach ($order as $index => $question_id) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($questions_table, 
                        ['order' => $index + 1],
                        ['id' => $question_id]
                    );
                }
                echo '<div class="updated notice"><p>Question order saved!</p></div>';
                break;            case 'save_options':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $options = isset($_POST['options']) ? json_decode(stripslashes(wp_unslash($_POST['options'])), true) : array();
                // Sanitize options array
                $options = is_array($options) ? array_map(function($opt) {
                    return [
                        'text' => isset($opt['text']) ? sanitize_text_field($opt['text']) : '',
                        'points' => isset($opt['points']) ? intval($opt['points']) : 0
                    ];
                }, $options) : array();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching                
                if (is_array($options)) {
                    // Delete existing options
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching                
                    $wpdb->delete($options_table, ['question_id' => $question_id]);
                    
                    // Insert new options
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching                
                    foreach ($options as $option) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching                
                        $wpdb->insert($options_table, [
                            'question_id' => $question_id,
                            'option_text' => sanitize_text_field($option['text']),
                            'points' => intval($option['points'])
                        ]);
                    }
                    echo '<div class="updated notice"><p>Options saved!</p></div>';
                }
                break;            case 'bulk_category_update':
                //  phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $question_ids = isset($_POST['question_ids']) ? array_map('intval', explode(',', wp_unslash($_POST['question_ids']))) : array();
                $category_id = !empty($_POST['bulk_category_id']) ? intval($_POST['bulk_category_id']) : null;
                $action_type = isset($_POST['bulk_action_type']) ? sanitize_text_field(wp_unslash($_POST['bulk_action_type'])) : '';
                
                if (!empty($question_ids)) {
                    foreach ($question_ids as $question_id) {
                        if ($action_type === 'remove-category') {
                            // PCP: Direct DB update for bulk category removal (admin action, no caching needed).
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->update($questions_table, 
                                ['category_id' => null],
                                ['id' => $question_id, 'quiz_id' => $quiz_id]
                            );
                        } else if ($action_type === 'set-category' && $category_id) {
                            // PCP: Direct DB update for bulk category set (admin action, no caching needed).
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->update($questions_table, 
                                ['category_id' => $category_id],
                                ['id' => $question_id, 'quiz_id' => $quiz_id]
                            );
                        }
                    }
                    echo '<div class="updated notice"><p>Categories updated for ' . count($question_ids) . ' questions!</p></div>';
                }
                break;            case 'remove_single_category':
                $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
                // PCP: Direct DB update for single category removal (admin action, no caching needed).
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($questions_table, 
                    ['category_id' => null],
                    ['id' => $question_id, 'quiz_id' => $quiz_id]
                );
                echo '<div class="updated notice"><p>Category removed from question!</p></div>';
                break;

            case 'toggle_required':
                $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
                $required = isset($_POST['required']) && $_POST['required'] === 'true' ? 1 : 0;
                
                // PCP: Direct DB update for toggling required (admin action, no caching needed).
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($questions_table, 
                    ['required' => $required],
                    ['id' => $question_id, 'quiz_id' => $quiz_id]
                );                
                $status = $required ? 'required' : 'optional';
                echo '<div class="updated notice"><p>Question marked as ' . esc_html($status) . '!</p></div>';
                break;
        }
    }    // Get questions ordered by the order column with category information
    // PCP: Direct DB select for getting questions with category info (admin/reporting context, no caching needed).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $questions = $wpdb->get_results($wpdb->prepare("SELECT q.*, c.name as category_name, c.color as category_color FROM $questions_table q  LEFT JOIN {$wpdb->prefix}qb_categories c ON q.category_id = c.id  WHERE q.quiz_id = %d  ORDER BY q.`order` ASC", $quiz_id));
    ?>
    <div class="wrap">
        <h1>Manage Questions for: <?php echo esc_html($quiz->title); ?></h1>

        <div class="qb-questions-container">
            <div class="qb-actions">
                <!-- <a href="<?php echo esc_url(add_query_arg(array(
                    'action' => 'export_quiz_attempts',
                    'quiz_id' => $quiz_id,
                    '_wpnonce' => wp_create_nonce('export_quiz_attempts_' . $quiz_id)
                ))); ?>" class="button button-primary">
                    Export Quiz Attempts
                </a> -->
            </div>            <h2>Add New Question</h2>
            <form method="post" class="qb-form">
                <?php wp_nonce_field('qb_manage_questions'); ?>
                <input type="hidden" name="action" value="add_question">
                <table class="form-table">
                    <tr>
                        <th><label for="question_text">Question</label></th>
                        <td>
                            <input name="question_text" type="text" id="question_text" class="regular-text" required>
                            <p class="description">Enter the question text</p>
                        </td>
                    </tr>                    <?php
                    // Check if categories exist and show dropdown
                    require_once plugin_dir_path(__FILE__) . '../includes/db/class-categories-db.php';
                    $categories_db = new QB_Categories_DB();
                    $categories = $categories_db->get_all_categories();
                    if (!empty($categories)): ?>
                    <tr>
                        <th><label for="category_id">Category</label></th>
                        <td>
                            <select name="category_id" id="category_id">
                                <option value="">-- Select Category (Optional) --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>">
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select a category to organize this question (optional)</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="question_required">Required Question</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="question_required" id="question_required" value="1">
                                Check this box if users must answer this question to proceed/submit the quiz
                            </label>
                            <p class="description"><strong>Note:</strong> This setting works independently of category selection.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Add Question', 'primary', 'submit', false); ?>
            </form>            <hr>

            <!-- Toggle Required Form - Always present regardless of categories -->
            <form method="post" id="toggle-required-form" style="display:none;">
                <?php wp_nonce_field('qb_manage_questions'); ?>
                <input type="hidden" name="action" value="toggle_required">
                <input type="hidden" name="question_id" id="toggle-required-question-id">
                <input type="hidden" name="required" id="toggle-required-status">
            </form>

            <?php if (!empty($categories)): ?>
            <!-- Category Management Tools -->
            <div class="qb-category-tools">
                <h3>Category Management Tools</h3>
                <div class="category-actions">
                    <div class="bulk-category-change">
                        <label for="filter-category">Filter by Category:</label>
                        <select id="filter-category">
                            <option value="">All Categories</option>
                            <option value="none">No Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="bulk-operations">
                        <label>Bulk Actions:</label>
                        <select id="bulk-action">
                            <option value="">Choose Action</option>
                            <option value="set-category">Set Category</option>
                            <option value="remove-category">Remove Category</option>
                        </select>
                        <select id="bulk-category" style="display:none;">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="apply-bulk-action" class="button">Apply to Selected</button>
                        <button type="button" id="select-all-questions" class="button">Select All</button>
                        <button type="button" id="deselect-all-questions" class="button">Deselect All</button>
                    </div>
                </div>
                
                <form method="post" id="bulk-category-form" style="display:none;">
                    <?php wp_nonce_field('qb_manage_questions'); ?>
                    <input type="hidden" name="action" value="bulk_category_update">
                    <input type="hidden" name="question_ids" id="bulk-question-ids">
                    <input type="hidden" name="bulk_category_id" id="bulk-category-id">
                    <input type="hidden" name="bulk_action_type" id="bulk-action-type">
                </form>
                  <form method="post" id="remove-category-form" style="display:none;">
                    <?php wp_nonce_field('qb_manage_questions'); ?>
                    <input type="hidden" name="action" value="remove_single_category">
                    <input type="hidden" name="question_id" id="remove-category-question-id">
                </form>
            </div>
            <hr>
            <?php endif; ?>
            <!-- phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching                    -->
            <h2>Questions <span id="question-count"><?php echo count($questions); ?></span></h2>
            
            <?php if (empty($categories)): ?>
            <div class="notice notice-info">
                <p><strong>Tip:</strong> You can mark questions as required by using the "Required" checkbox next to each question, even without creating categories. Categories are optional for organizing questions.</p>
            </div>
            <?php endif; ?>
            
            <div id="questions-list" class="sortable">
                <!-- phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching                 -->
                <?php if ($questions): ?>
                    <?php foreach ($questions as $question): 
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared                    
                        $options = $wpdb->get_results($wpdb->prepare( "SELECT * FROM $options_table WHERE question_id = %d ORDER BY id ASC",  $question->id ));  ?>                        <div class="accordion" data-question-id="<?php echo esc_attr($question->id); ?>" data-category-id="<?php echo esc_attr($question->category_id ?: ''); ?>">
                            <div class="accordion-header">
                                <input type="checkbox" class="question-checkbox" value="<?php echo esc_attr($question->id); ?>" style="margin-right: 10px;">
                                <span class="handle">⋮</span>                                <span class="question-text">
                                    <?php echo esc_html($question->question); ?>
                                    <?php if ($question->required): ?>
                                        <span class="qb-required-badge" style="background-color: #e74c3c; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 6px; font-weight: 500;">
                                            REQUIRED
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($question->category_name): ?>
                                        <span class="qb-category-badge" style="background-color: <?php echo esc_attr($question->category_color); ?>;">
                                            <?php echo esc_html($question->category_name); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>                                <div class="question-actions">                                    <label class="required-toggle" style="margin-right: 10px; display: flex; align-items: center; font-size: 12px;">
                                        <input type="checkbox" class="required-checkbox" data-question-id="<?php echo esc_attr($question->id); ?>" <?php echo esc_attr($question->required ? 'checked' : ''); ?> style="margin-right: 5px;">
                                        Required
                                    </label><button class="button edit-question" data-id="<?php echo esc_attr($question->id); ?>">Edit</button>
                                    <button class="button remove-category-btn" data-id="<?php echo esc_attr($question->id); ?>" style="display: <?php echo esc_attr($question->category_id ? 'inline-block' : 'none'); ?>;">Remove Category</button>
                                    <button class="button delete-question" data-id="<?php echo esc_attr($question->id); ?>">Delete</button>
                                </div>
                            </div>
                            <div class="accordion-body">
                                <div class="options-management">
                                    <h4>Options</h4>
                                    <button type="button" class="button add-option-btn">Add Option</button>
                                    
                                    <form method="post" class="qb-form">
                                        <?php wp_nonce_field('qb_manage_questions'); ?>
                                        <input type="hidden" name="action" value="save_options">                                        <input type="hidden" name="question_id" value="<?php echo esc_attr($question->id); ?>">
                                        <input type="hidden" name="options" value='<?php
                                            echo esc_attr(json_encode(array_map(function($opt) {
                                                return ['text' => $opt->option_text, 'points' => $opt->points];
                                            }, $options)));
                                        ?>'>
                                        
                                        <div class="options-list options-sortable">
                                            <!-- Options will be added here dynamically -->
                                        </div>
                                        
                                        <button type="button" class="button button-primary save-options-btn">Save Options</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No questions yet.</p>
                <?php endif; ?>
            </div>

            <form method="post" id="save-order-form" style="display:none;">
                <?php wp_nonce_field('qb_manage_questions'); ?>
                <input type="hidden" name="action" value="save_order">
                <input type="hidden" name="order" id="questions-order">
                <?php submit_button('Save Order', 'primary', 'submit', false); ?>
            </form>
        </div>
    </div>    <style>
        .qb-questions-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .qb-category-tools {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .qb-category-tools h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        .category-actions {
            display: flex;
            gap: 30px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .bulk-category-change, .bulk-operations {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .bulk-operations select, .bulk-category-change select {
            min-width: 150px;
        }
        .sortable {
            margin-bottom: 20px;
        }
        .accordion {
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #fff;
        }
        .accordion.hidden {
            display: none;
        }
        .accordion-header {
            background: #f1f1f1;
            padding: 10px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .accordion-header .handle {
            cursor: move;
            margin-right: 10px;
            color: #666;
            font-size: 20px;
        }        .accordion-header .question-text {
            flex-grow: 1;
            margin-left: 10px;
        }
        .question-checkbox {
            margin-right: 10px;
        }
        .qb-category-badge {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
            font-weight: 500;
        }        .accordion-header .question-actions {
            margin-left: 10px;
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .required-toggle {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 11px !important;
            white-space: nowrap;
        }
        .required-toggle input[type="checkbox"] {
            margin-right: 3px !important;
        }
        .remove-category-btn {
            background-color: #f39c12 !important;
            color: white !important;
            border-color: #f39c12 !important;
        }
        .remove-category-btn:hover {
            background-color: #e67e22 !important;
            border-color: #e67e22 !important;
        }
        .accordion-body {
            display: none;
            padding: 15px;
            background: #fff;
        }
        .accordion-body.active {
            display: block;
        }
        .options-management {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .options-list {
            margin-bottom: 20px;
        }
        .option-item {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .option-item .handle {
            cursor: move;
            color: #666;
            padding: 5px;
        }
        .option-item input[type="text"] {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .option-item input[type="number"] {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .option-item .actions {
            display: flex;
            gap: 5px;
        }
        .add-option-btn {
            margin-bottom: 20px;
        }
        .save-options-btn {
            margin-top: 20px;
        }
        .ui-sortable-helper {
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .qb-actions {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .edit-question-form,
        .edit-option-form {
            position: relative;
            z-index: 1;
        }
        .edit-question-form button,
        .edit-option-form button {
            position: relative;
            z-index: 2;
        }
        #question-count {
            color: #666;
            font-size: 0.9em;
        }
    </style>    <script>
    jQuery(document).ready(function($) {
        // Categories data for JavaScript
        <?php
        $categories_js = array();
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $categories_js[] = array(
                    'id' => $category->id,
                    'name' => $category->name
                );
            }
        }        ?>        var categoriesData = <?php echo wp_json_encode($categories_js); ?>;
        var qbNonceField = <?php echo wp_json_encode(wp_nonce_field('qb_manage_questions', '_wpnonce', true, false)); ?>;
        
        // Initialize sortable
        $('#questions-list').sortable({
            handle: '.handle',
            update: function(event, ui) {
                var order = [];
                $('.accordion').each(function() {
                    order.push($(this).data('question-id'));
                });
                $('#questions-order').val(JSON.stringify(order));
                $('#save-order-form').show();
            }
        });

        // Category filtering
        $('#filter-category').on('change', function() {
            var selectedCategory = $(this).val();
            var visibleCount = 0;
            
            $('.accordion').each(function() {
                var questionCategoryId = $(this).data('category-id');
                var show = false;
                
                if (selectedCategory === '') {
                    // Show all
                    show = true;
                } else if (selectedCategory === 'none') {
                    // Show only questions without category
                    show = !questionCategoryId;
                } else {
                    // Show only questions with specific category
                    show = questionCategoryId == selectedCategory;
                }
                
                if (show) {
                    $(this).removeClass('hidden');
                    visibleCount++;
                } else {
                    $(this).addClass('hidden');
                }
            });
            
            // Update question count
            var totalCount = $('.accordion').length;
            $('#question-count').text(visibleCount + ' of ' + totalCount);
        });

        // Bulk action handling
        $('#bulk-action').on('change', function() {
            var action = $(this).val();
            if (action === 'set-category') {
                $('#bulk-category').show();
            } else {
                $('#bulk-category').hide();
            }
        });

        // Select/deselect all questions
        $('#select-all-questions').on('click', function() {
            $('.accordion:not(.hidden) .question-checkbox').prop('checked', true);
        });

        $('#deselect-all-questions').on('click', function() {
            $('.question-checkbox').prop('checked', false);
        });

        // Apply bulk action
        $('#apply-bulk-action').on('click', function() {
            var action = $('#bulk-action').val();
            var selectedQuestions = [];
            
            $('.question-checkbox:checked').each(function() {
                selectedQuestions.push($(this).val());
            });
            
            if (selectedQuestions.length === 0) {
                alert('Please select at least one question.');
                return;
            }
            
            if (!action) {
                alert('Please select an action.');
                return;
            }
            
            if (action === 'set-category') {
                var categoryId = $('#bulk-category').val();
                if (!categoryId) {
                    alert('Please select a category.');
                    return;
                }
                $('#bulk-category-id').val(categoryId);
            }
            
            $('#bulk-question-ids').val(selectedQuestions.join(','));
            $('#bulk-action-type').val(action);
            $('#bulk-category-form').submit();
        });        // Remove category from single question
        $('.remove-category-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (confirm('Are you sure you want to remove the category from this question?')) {
                var questionId = $(this).data('id');
                $('#remove-category-question-id').val(questionId);
                $('#remove-category-form').submit();
            }
        });

        // Toggle required status
        $('.required-checkbox').on('change', function(e) {
            e.stopPropagation();
            
            var questionId = $(this).data('question-id');
            var isRequired = $(this).is(':checked');
            
            console.log('Required checkbox changed:', {
                questionId: questionId,
                isRequired: isRequired,
                element: this
            });
            
            if (!questionId) {
                console.error('No question ID found for required checkbox');
                alert('Error: Could not update question status. Please refresh the page and try again.');
                return;
            }
            
            // Check if the form exists
            if ($('#toggle-required-form').length === 0) {
                console.error('Toggle required form not found in DOM');
                alert('Error: Form not found. Please refresh the page and try again.');
                return;
            }
            
            $('#toggle-required-question-id').val(questionId);
            $('#toggle-required-status').val(isRequired ? 'true' : 'false');
            
            console.log('Submitting toggle form with:', {
                questionId: $('#toggle-required-question-id').val(),
                status: $('#toggle-required-status').val()
            });
            
            $('#toggle-required-form').submit();
        });

        // Toggle accordion
        $('.accordion-header').on('click', function(e) {
            if (!$(e.target).is('.handle, .edit-question, .delete-question, .remove-category-btn, .question-checkbox, .required-checkbox, .required-toggle, .edit-question-form *, .edit-option-form *')) {
                $(this).next('.accordion-body').slideToggle();
            }
        });// Edit question
        $('.edit-question').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $header = $(this).closest('.accordion-header');
            var questionText = $header.find('.question-text').text();
            var questionId = $(this).data('id');
            var currentCategoryId = $header.closest('.accordion').data('category-id') || '';
            
            // Clean question text (remove badge text)
            questionText = questionText.replace(/\s*(REQUIRED|[\w\s]+)\s*$/, '').trim();
            
            // Check if question is required (look for REQUIRED badge)
            var isRequired = $header.find('.qb-required-badge').length > 0;
            
            // Build category dropdown HTML
            var categoryDropdown = '';
            if (categoriesData.length > 0) {
                categoryDropdown = `
                    <select name="category_id" style="margin-left: 10px; margin-right: 10px;">
                        <option value="">-- No Category --</option>`;
                categoriesData.forEach(function(category) {
                    var selected = (category.id == currentCategoryId) ? 'selected' : '';
                    categoryDropdown += `<option value="${category.id}" ${selected}>${category.name}</option>`;
                });
                categoryDropdown += '</select>';
            }
            
            // Build required checkbox HTML
            var requiredCheckbox = `
                <label style="margin-left: 10px; margin-right: 10px; display: flex; align-items: center; font-size: 12px;">
                    <input type="checkbox" name="question_required" value="1" ${isRequired ? 'checked' : ''} style="margin-right: 5px;">
                    Required
                </label>`;
              $header.html(`                <form method="post" class="qb-form edit-question-form" style="display: flex; align-items: center; gap: 10px;">
                    ${qbNonceField}
                    <input type="hidden" name="action" value="edit_question">
                    <input type="hidden" name="question_id" value="${questionId}">
                    <input type="text" name="question_text" value="${questionText}" required style="flex-grow: 1;">
                    ${categoryDropdown}
                    ${requiredCheckbox}
                    <button type="submit" class="button button-primary">Save</button>
                    <button type="button" class="button cancel-edit">Cancel</button>
                </form>
            `);

            // Prevent accordion toggle for edit form
            $header.find('.edit-question-form').on('click', function(e) {
                e.stopPropagation();
            });
        });

        // Delete question
        $('.delete-question').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this question?')) {
                var questionId = $(this).data('id');                var $form = $('<form method="post">')
                    .append(qbNonceField)
                    .append($('<input type="hidden" name="action" value="delete_question">'))
                    .append($('<input type="hidden" name="question_id" value="' + questionId + '">'));
                $('body').append($form);
                $form.submit();
            }
        });

        // Initialize options sortable
        $('.options-sortable').sortable({
            handle: '.handle',
            update: function() {
                updateOptionsOrder($(this));
            }
        });

        // Add new option
        $('.add-option-btn').on('click', function() {
            var $container = $(this).closest('.options-management');
            var $optionsList = $container.find('.options-list');
            var optionCount = $optionsList.find('.option-item').length;
            
            var $newOption = $(`
                <div class="option-item">
                    <span class="handle">⋮</span>
                    <input type="text" placeholder="Option text" required>
                    <input type="number" placeholder="Points" value="0" required>
                    <div class="actions">
                        <button type="button" class="button remove-option">Remove</button>
                    </div>
                </div>
            `);
            
            $optionsList.append($newOption);
            updateOptionsOrder($container.find('.options-sortable'));
        });

        // Remove option
        $(document).on('click', '.remove-option', function() {
            $(this).closest('.option-item').remove();
            updateOptionsOrder($(this).closest('.options-sortable'));
        });

        // Update options order and prepare for saving
        function updateOptionsOrder($sortable) {
            var $container = $sortable.closest('.options-management');
            var options = [];
            
            $sortable.find('.option-item').each(function() {
                options.push({
                    text: $(this).find('input[type="text"]').val(),
                    points: $(this).find('input[type="number"]').val()
                });
            });
            
            $container.find('input[name="options"]').val(JSON.stringify(options));
        }

        // Save options
        $('.save-options-btn').on('click', function() {
            var $form = $(this).closest('form');
            var options = [];
            var isValid = true;
            
            $form.find('.option-item').each(function() {
                var $text = $(this).find('input[type="text"]');
                var $points = $(this).find('input[type="number"]');
                
                if (!$text.val()) {
                    isValid = false;
                    $text.addClass('error');
                } else {
                    $text.removeClass('error');
                }
                
                options.push({
                    text: $text.val(),
                    points: $points.val()
                });
            });
            
            if (!isValid) {
                alert('Please fill in all option texts');
                return;
            }
            
            $form.find('input[name="options"]').val(JSON.stringify(options));
            $form.submit();
        });

        // Initialize existing options
        $('.options-management').each(function() {
            var $container = $(this);
            var $optionsList = $container.find('.options-list');
            var options = JSON.parse($container.find('input[name="options"]').val() || '[]');
            
            options.forEach(function(option) {
                var $option = $(`
                    <div class="option-item">
                        <span class="handle">⋮</span>
                        <input type="text" value="${option.text}" required>
                        <input type="number" value="${option.points}" required>
                        <div class="actions">
                            <button type="button" class="button remove-option">Remove</button>
                        </div>
                    </div>
                `);
                $optionsList.append($option);
            });
        });

        // Cancel edit
        $(document).on('click', '.cancel-edit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            location.reload();
        });

        // Initialize question count
        $('#question-count').text($('.accordion').length);
        
        // Add visual feedback for required checkbox
        $('#question_required').on('change', function() {
            var isChecked = $(this).is(':checked');
            var $description = $(this).closest('td').find('.description');
            if (isChecked) {
                $description.html('<strong>Note:</strong> This question will be marked as REQUIRED. Users must answer it to submit the quiz.');
                $description.css('color', '#d63638');
            } else {
                $description.html('<strong>Note:</strong> This setting works independently of category selection.');
                $description.css('color', '#646970');
            }
        });
    });
    </script>
    <?php
}
