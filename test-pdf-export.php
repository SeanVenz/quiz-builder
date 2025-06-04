<?php
/**
 * Test script for PDF Export functionality
 * This script tests the PDF generation functions without requiring WordPress
 */

// Mock data for testing
$mock_quiz = (object) [
    'id' => 1,
    'title' => 'Sample Quiz - JavaScript Basics'
];

$mock_attempt = (object) [
    'id' => 1,
    'quiz_id' => 1,
    'score' => 8,
    'total_points' => 10,
    'created_at' => '2025-06-04 10:30:00'
];

$mock_answers = [
    (object) [
        'question_text' => 'What does "var" keyword do in JavaScript?',
        'selected_text' => 'Declares a variable',
        'correct_text' => 'Declares a variable',
        'selected_option' => 'A',
        'correct_option' => 'A'
    ],
    (object) [
        'question_text' => 'Which method adds an element to the end of an array?',
        'selected_text' => 'append()',
        'correct_text' => 'push()',
        'selected_option' => 'B',
        'correct_option' => 'C'
    ],
    (object) [
        'question_text' => 'What is the result of 2 + "2" in JavaScript?',
        'selected_text' => '"22"',
        'correct_text' => '"22"',
        'selected_option' => 'A',
        'correct_option' => 'A'
    ]
];

// Mock functions that would normally be WordPress functions
function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function current_time($format) {
    return date($format);
}

/**
 * Generate HTML content for PDF (copied from plugin)
 */
function qb_generate_pdf_html($quiz, $attempt, $answers, $percentage, $current_date) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($quiz->title); ?> - Quiz Results</title>
        <style>
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
            <div class="score">Final Score: <?php echo $attempt->score; ?> / <?php echo $attempt->total_points; ?> (<?php echo $percentage; ?>%)</div>
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
            <p>Generated on <?php echo current_time('F j, Y \a\t g:i A'); ?></p>
            <p>Quiz Builder Plugin - WordPress</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Test the PDF generation
echo "Testing PDF Export HTML Generation...\n\n";

$percentage = round(($mock_attempt->score / $mock_attempt->total_points) * 100);
$current_date = current_time('Y-m-d H:i:s');

$html_content = qb_generate_pdf_html($mock_quiz, $mock_attempt, $mock_answers, $percentage, $current_date);

// Save the test HTML to a file
$test_file = 'test-pdf-output.html';
file_put_contents($test_file, $html_content);

echo "✅ PDF HTML generated successfully!\n";
echo "📄 Test output saved to: {$test_file}\n";
echo "🌐 Open the file in a browser to preview the PDF layout\n\n";

// Display some key information
echo "Test Data Summary:\n";
echo "- Quiz: {$mock_quiz->title}\n";
echo "- Score: {$mock_attempt->score}/{$mock_attempt->total_points} ({$percentage}%)\n";
echo "- Questions: " . count($mock_answers) . "\n";
echo "- Correct Answers: " . count(array_filter($mock_answers, function($a) { 
    return $a->selected_option == $a->correct_option; 
})) . "\n\n";

echo "✅ PDF export functionality test completed!\n";
echo "Next steps:\n";
echo "1. Open {$test_file} in a web browser\n";
echo "2. Use browser's Print function to test PDF conversion\n";
echo "3. Verify styling and layout are correct\n";
