<?php
/**
 * Attempt Details Template
 *
 * Variables expected:
 * @var object $quiz
 * @var object|null $user
 * @var object $attempt
 * @var array $answers
 * @var wpdb $wpdb
 */
?>
<div class="attempt-details">
    <h2>Quiz Attempt Details</h2>    <div class="attempt-info">
        <p><strong>Quiz:</strong> <?php echo esc_html($quiz->title); ?></p>
        <p><strong>User:</strong> <?php echo esc_html($user ? $user->display_name : 'Guest'); ?></p>
        <p><strong>Score:</strong> <?php echo esc_html($attempt->score); ?>/<?php echo esc_html($attempt->total_points); ?> (<?php echo esc_html(round(($attempt->score / $attempt->total_points) * 100)); ?>%)</p>
        <p><strong>Date:</strong> <?php echo esc_html($attempt->created_at); ?></p>
        <a href="<?php echo esc_url( home_url( '/quiz-results/' .  $attempt->random_id ) ); ?>">View Answers</a>    </div>
    <h3>Answers</h3>
    <table class="widefat fixed striped">
        <thead><tr><th>Question</th><th>Answer</th><th>Points</th></tr></thead>
        <tbody>
        <?php foreach ($answers as $answer):
            // PCP: Direct DB select for question (reporting context, no caching needed).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}qb_questions WHERE id = %d", $answer['question_id']));
            // PCP: Direct DB select for option (reporting context, no caching needed).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $option = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}qb_options WHERE id = %d", $answer['option_id']));
            if ($question && $option): ?>
                <tr>
                    <td><?php echo esc_html($question->question); ?></td>
                    <td><?php echo esc_html($option->option_text); ?></td>
                    <td><?php echo esc_html($option->points); ?></td>
                </tr>
            <?php endif;
        endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 20px;"><a href="#" class="button" onclick="document.getElementById('attempt-details-modal').style.display='none';">Close</a></p>
</div>
