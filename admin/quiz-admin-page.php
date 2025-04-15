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
        'Manage Questions', // menu title (won’t show since it's null)
        'manage_options',
        'qb-manage-questions',
        'qb_manage_questions_page'
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
