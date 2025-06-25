<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html($quiz->title); ?> - Quiz Results</title>
    <style>
        /* Inline critical styles for PDF generation */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2271b1;
            margin: 0;
        }
        .score-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
        }
        .score-summary .score {
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        .answers-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .answers-table th,
        .answers-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .answers-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .correct-answer {
            background-color: #d4edda;
        }
        .incorrect-answer {
            background-color: #f8d7da;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo esc_html($quiz->title); ?></h1>
        <p>Quiz Results Report</p>
    </div>

    <div class="score-summary">
        <div class="score">
            Final Score: <?php echo esc_html($attempt->score); ?> / <?php echo esc_html($attempt->total_points); ?> 
            (<?php echo esc_html($percentage); ?>%)
        </div>
        <p>Date Completed: <?php echo esc_html($current_date); ?></p>
    </div>
    
    <?php if (!empty($answers)): ?>
    <h2>Your Answers</h2>
    <table class="answers-table">
        <thead>
            <tr>
                <th>Question</th>
                <th>Your Answer</th>
                <th>Correct Answer</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($answers as $answer): 
                $is_correct = $answer->selected_option == $answer->correct_option;
            ?>
            <tr class="<?php echo $is_correct ? 'correct-answer' : 'incorrect-answer'; ?>">
                <td><?php echo esc_html($answer->question_text); ?></td>
                <td><?php echo esc_html($answer->selected_text); ?></td>
                <td><?php echo esc_html($answer->correct_text); ?></td>
                <td><?php echo $is_correct ? '✓ Correct' : '✗ Incorrect'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="footer">
        <p>Generated on <?php echo esc_html(current_time('F j, Y \a\t g:i A')); ?></p>
        <p>Quiz Builder Plugin - WordPress</p>
    </div>
</body>
</html>
