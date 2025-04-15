<?php
if (!defined('ABSPATH')) exit;

function qb_manage_questions_page() {
    global $wpdb;

    $quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}qb_quizzes WHERE id = %d", $quiz_id));

    if (!$quiz) {
        echo '<div class="error"><p>Invalid quiz ID.</p></div>';
        return;
    }

    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';

    // Add question
    if (isset($_POST['qb_add_question'])) {
        $question_text = sanitize_text_field($_POST['question_text']);
        if (!empty($question_text)) {
            $wpdb->insert($questions_table, [
                'quiz_id' => $quiz_id,
                'question' => $question_text,
            ]);
            echo '<div class="updated notice"><p>Question added!</p></div>';
        }
    }

    // Add option to question
    if (isset($_POST['qb_add_option'])) {
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
    }

    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d", $quiz_id));
    ?>
    <div class="wrap">
        <h1>Manage Questions for: <?php echo esc_html($quiz->title); ?></h1>

        <h2>Add New Question</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="question_text">Question</label></th>
                    <td><input name="question_text" type="text" id="question_text" class="regular-text" required></td>
                </tr>
            </table>
            <?php submit_button('Add Question', 'primary', 'qb_add_question'); ?>
        </form>

        <hr>

        <h2>Questions</h2>
        <style>
            .accordion { margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
            .accordion-header { background: #f1f1f1; padding: 10px; cursor: pointer; font-weight: bold; }
            .accordion-body { display: none; padding: 10px; }
            .accordion-body table { width: 100%; }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.accordion-header').forEach(header => {
                    header.addEventListener('click', () => {
                        const body = header.nextElementSibling;
                        body.style.display = body.style.display === 'block' ? 'none' : 'block';
                    });
                });
            });
        </script>

        <?php if ($questions): ?>
            <?php foreach ($questions as $question): 
                $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM $options_table WHERE question_id = %d", $question->id));
            ?>
                <div class="accordion">
                    <div class="accordion-header">
                        Q<?php echo esc_html($question->id); ?>: <?php echo esc_html($question->question); ?>
                    </div>
                    <div class="accordion-body">
                        <h4>Options</h4>
                        <?php if ($options): ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Option</th>
                                        <th>Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($options as $opt): ?>
                                        <tr>
                                            <td><?php echo esc_html($opt->option_text); ?></td>
                                            <td><?php echo esc_html($opt->points); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No options yet.</p>
                        <?php endif; ?>

                        <form method="post" style="margin-top:10px;">
                            <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
                            <input type="text" name="option_text" placeholder="Option text" required>
                            <input type="number" name="points" placeholder="Points" required style="width:80px;">
                            <?php submit_button('Add Option', 'secondary', 'qb_add_option', false); ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No questions yet.</p>
        <?php endif; ?>
    </div>
    <?php
}
