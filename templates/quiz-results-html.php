<?php
/**
 * Quiz Results Template
 *
 * Variables expected:
 * @var object $quiz
 * @var int $score
 * @var int $total_possible_points
 */
?>
<div class="quiz-result" style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <h2 style="color: #333; margin-bottom: 20px; text-align: center;">Quiz Results</h2>
    <h3 style="color: #444; margin-bottom: 15px; text-align: center;">
        <?php echo esc_html($quiz->title); ?>
    </h3>
    <div style="margin-bottom: 20px; text-align: center;">
        <p style="font-size: 24px; margin-bottom: 10px;">Your Score: <strong><?php echo esc_html($score); ?>/<?php echo esc_html($total_possible_points); ?></strong></p>
        <p style="font-size: 20px;">Percentage: <strong><?php echo esc_html($total_possible_points > 0 ? round(($score / $total_possible_points) * 100) : 0); ?>%</strong></p>
    </div>    <div style="margin-top: 20px; text-align: center;">
        <a href="<?php echo esc_url(home_url()); ?>" class="button button-primary" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">Go Home</a>
    </div>
</div>
