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
    $title = sanitize_text_field($_POST['quiz_title']);
    $description = sanitize_textarea_field($_POST['quiz_description']);
    
    if (!empty($title)) {
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
    $quiz_id = intval($_POST['quiz_id']);
    $title = sanitize_text_field($_POST['quiz_title']);
    $description = sanitize_textarea_field($_POST['quiz_description']);
    
    if (!empty($title)) {
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
    $quiz_id = intval($_POST['quiz_id']);
    
    // Delete related records first
    $wpdb->delete($wpdb->prefix . 'qb_options', ['question_id' => 
        $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}qb_questions WHERE quiz_id = %d",
            $quiz_id
        ))
    ]);
    $wpdb->delete($wpdb->prefix . 'qb_questions', ['quiz_id' => $quiz_id]);
    $wpdb->delete($wpdb->prefix . 'qb_attempts', ['quiz_id' => $quiz_id]);
    
    // Delete the quiz
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
    global $wpdb;

    // Add required scripts and styles
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

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
        
        switch ($_POST['action']) {
            case 'add_question':
                $question_text = sanitize_text_field($_POST['question_text']);
                if (!empty($question_text)) {
                    // Get the next order number
                    $next_order = $wpdb->get_var($wpdb->prepare(
                        "SELECT MAX(`order`) + 1 FROM $questions_table WHERE quiz_id = %d",
                        $quiz_id
                    )) ?: 1;

                    $wpdb->insert($questions_table, [
                        'quiz_id' => $quiz_id,
                        'question' => $question_text,
                        'order' => $next_order
                    ]);
                    echo '<div class="updated notice"><p>Question added!</p></div>';
                }
                break;

            case 'edit_question':
                $question_id = intval($_POST['question_id']);
                $question_text = sanitize_text_field($_POST['question_text']);
                if (!empty($question_text)) {
                    $wpdb->update($questions_table, 
                        ['question' => $question_text],
                        ['id' => $question_id]
                    );
                    echo '<div class="updated notice"><p>Question updated!</p></div>';
                }
                break;

            case 'delete_question':
                $question_id = intval($_POST['question_id']);
                // Get the order of the question being deleted
                $deleted_order = $wpdb->get_var($wpdb->prepare(
                    "SELECT `order` FROM $questions_table WHERE id = %d",
                    $question_id
                ));
                
                // Delete the question
                $wpdb->delete($questions_table, ['id' => $question_id]);
                
                // Update orders of remaining questions
                $wpdb->query($wpdb->prepare(
                    "UPDATE $questions_table SET `order` = `order` - 1 
                     WHERE quiz_id = %d AND `order` > %d",
                    $quiz_id, $deleted_order
                ));
                
                echo '<div class="updated notice"><p>Question deleted!</p></div>';
                break;

            case 'add_option':
                $question_id = intval($_POST['question_id']);
                $option_text = sanitize_text_field($_POST['option_text']);
                $points = intval($_POST['points']);
                if (!empty($option_text)) {
                    $wpdb->insert($options_table, [
                        'question_id' => $question_id,
                        'option_text' => $option_text,
                        'points' => $points,
                    ]);
                    echo '<div class="updated notice"><p>Option added!</p></div>';
                }
                break;

            case 'edit_option':
                $option_id = intval($_POST['option_id']);
                $option_text = sanitize_text_field($_POST['option_text']);
                $points = intval($_POST['points']);
                if (!empty($option_text)) {
                    $wpdb->update($options_table, 
                        ['option_text' => $option_text, 'points' => $points],
                        ['id' => $option_id]
                    );
                    echo '<div class="updated notice"><p>Option updated!</p></div>';
                }
                break;

            case 'delete_option':
                $option_id = intval($_POST['option_id']);
                $wpdb->delete($options_table, ['id' => $option_id]);
                echo '<div class="updated notice"><p>Option deleted!</p></div>';
                break;

            case 'save_order':
                $order = json_decode(stripslashes($_POST['order']));
                foreach ($order as $index => $question_id) {
                    $wpdb->update($questions_table, 
                        ['order' => $index + 1],
                        ['id' => $question_id]
                    );
                }
                echo '<div class="updated notice"><p>Question order saved!</p></div>';
                break;

            case 'save_options':
                $question_id = intval($_POST['question_id']);
                $options = json_decode(stripslashes($_POST['options']), true);
                
                if (is_array($options)) {
                    // Delete existing options
                    $wpdb->delete($options_table, ['question_id' => $question_id]);
                    
                    // Insert new options
                    foreach ($options as $option) {
                        $wpdb->insert($options_table, [
                            'question_id' => $question_id,
                            'option_text' => sanitize_text_field($option['text']),
                            'points' => intval($option['points'])
                        ]);
                    }
                    echo '<div class="updated notice"><p>Options saved!</p></div>';
                }
                break;
        }
    }

    // Get questions ordered by the order column
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $questions_table WHERE quiz_id = %d ORDER BY `order` ASC, id ASC", 
        $quiz_id
    ));
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
            </div>

            <h2>Add New Question</h2>
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
                    </tr>
                </table>
                <?php submit_button('Add Question', 'primary', 'submit', false); ?>
            </form>

            <hr>

            <h2>Questions</h2>
            <div id="questions-list" class="sortable">
                <?php if ($questions): ?>
                    <?php foreach ($questions as $question): 
                        $options = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM $options_table WHERE question_id = %d ORDER BY id ASC", 
                            $question->id
                        ));
                    ?>
                        <div class="accordion" data-question-id="<?php echo $question->id; ?>">
                            <div class="accordion-header">
                                <span class="handle">⋮</span>
                                <span class="question-text"><?php echo esc_html($question->question); ?></span>
                                <div class="question-actions">
                                    <button class="button edit-question" data-id="<?php echo $question->id; ?>">Edit</button>
                                    <button class="button delete-question" data-id="<?php echo $question->id; ?>">Delete</button>
                                </div>
                            </div>
                            <div class="accordion-body">
                                <div class="options-management">
                                    <h4>Options</h4>
                                    <button type="button" class="button add-option-btn">Add Option</button>
                                    
                                    <form method="post" class="qb-form">
                                        <?php wp_nonce_field('qb_manage_questions'); ?>
                                        <input type="hidden" name="action" value="save_options">
                                        <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
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
    </div>

    <style>
        .qb-questions-container {
            max-width: 1200px;
            margin: 0 auto;
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
        }
        .accordion-header .question-text {
            flex-grow: 1;
        }
        .accordion-header .question-actions {
            margin-left: 10px;
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
    </style>

    <script>
    jQuery(document).ready(function($) {
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

        // Toggle accordion
        $('.accordion-header').on('click', function(e) {
            if (!$(e.target).is('.handle, .edit-question, .delete-question, .edit-question-form *, .edit-option-form *')) {
                $(this).next('.accordion-body').slideToggle();
            }
        });

        // Edit question
        $('.edit-question').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $header = $(this).closest('.accordion-header');
            var questionText = $header.find('.question-text').text();
            var questionId = $(this).data('id');
            
            $header.html(`
                <form method="post" class="qb-form edit-question-form">
                    <?php echo wp_nonce_field('qb_manage_questions', '_wpnonce', true, false); ?>
                    <input type="hidden" name="action" value="edit_question">
                    <input type="hidden" name="question_id" value="${questionId}">
                    <input type="text" name="question_text" value="${questionText}" required>
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
                var questionId = $(this).data('id');
                var $form = $('<form method="post">')
                    .append('<?php echo wp_nonce_field('qb_manage_questions', '_wpnonce', true, false); ?>')
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
    });
    </script>
    <?php
}
