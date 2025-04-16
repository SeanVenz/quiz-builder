<?php
if (!defined('ABSPATH')) exit;

function qb_manage_questions_page() {
    global $wpdb;

    // Add required scripts and styles
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    $quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
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
                                <div class="options-list">
                                    <?php if ($options): ?>
                                        <table class="widefat striped">
                                            <thead>
                                                <tr>
                                                    <th>Option</th>
                                                    <th>Points</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($options as $opt): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($opt->option_text); ?></td>
                                                        <td><?php echo esc_html($opt->points); ?></td>
                                                        <td>
                                                            <button class="button edit-option" data-id="<?php echo $opt->id; ?>">Edit</button>
                                                            <button class="button delete-option" data-id="<?php echo $opt->id; ?>">Delete</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p>No options yet.</p>
                                    <?php endif; ?>

                                    <form method="post" class="qb-form add-option-form">
                                        <?php wp_nonce_field('qb_manage_questions'); ?>
                                        <input type="hidden" name="action" value="add_option">
                                        <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
                                        <input type="text" name="option_text" placeholder="Option text" required>
                                        <input type="number" name="points" placeholder="Points" required style="width:80px;">
                                        <?php submit_button('Add Option', 'secondary', 'submit', false); ?>
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
        .options-list {
            margin-top: 15px;
        }
        .add-option-form {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .add-option-form input[type="text"] {
            flex-grow: 1;
        }
        .ui-sortable-helper {
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .edit-question-form,
        .edit-option-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .edit-question-form input[type="text"],
        .edit-option-form input[type="text"] {
            flex-grow: 1;
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
            if (!$(e.target).is('.handle, .edit-question, .delete-question')) {
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

        // Edit option
        $('.edit-option').on('click', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            var optionText = $row.find('td:first').text();
            var points = $row.find('td:nth-child(2)').text();
            var optionId = $(this).data('id');
            
            $row.html(`
                <td colspan="3">
                    <form method="post" class="qb-form edit-option-form">
                        <?php echo wp_nonce_field('qb_manage_questions', '_wpnonce', true, false); ?>
                        <input type="hidden" name="action" value="edit_option">
                        <input type="hidden" name="option_id" value="${optionId}">
                        <input type="text" name="option_text" value="${optionText}" required>
                        <input type="number" name="points" value="${points}" required style="width:80px;">
                        <button type="submit" class="button button-primary">Save</button>
                        <button type="button" class="button cancel-edit">Cancel</button>
                    </form>
                </td>
            `);
        });

        // Delete option
        $('.delete-option').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this option?')) {
                var optionId = $(this).data('id');
                var $form = $('<form method="post">')
                    .append('<?php echo wp_nonce_field('qb_manage_questions', '_wpnonce', true, false); ?>')
                    .append($('<input type="hidden" name="action" value="delete_option">'))
                    .append($('<input type="hidden" name="option_id" value="' + optionId + '">'));
                $('body').append($form);
                $form.submit();
            }
        });

        // Cancel edit
        $(document).on('click', '.cancel-edit', function() {
            location.reload();
        });
    });
    </script>
    <?php
}
