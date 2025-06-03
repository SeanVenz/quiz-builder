<?php
if (!defined('ABSPATH')) exit;

function qb_onboarding_page() {
    ?>
    <div class="wrap">
        <h1>Welcome to Quiz Builder</h1>
        <p>Thank you for installing Quiz Builder! This plugin allows you to create and manage quizzes easily. Let's get started with a quick setup.</p>

        <div id="qb-onboarding">
            <div class="qb-step" id="qb-step-1">
                <h2>Step 1: Create a Quiz</h2>
                <p>Enter the title and description for your first quiz.</p>
                <form id="qb-create-quiz-form">
                    <label for="quiz-title">Quiz Title:</label>
                    <input type="text" id="quiz-title" name="quiz_title" required>
                    <br>
                    <label for="quiz-description">Description:</label>
                    <textarea id="quiz-description" name="quiz_description"></textarea>
                    <br>
                    <button type="button" id="qb-create-quiz-button">Next</button>
                </form>
            </div>

            <div class="qb-step" id="qb-step-2" style="display: none;">
                <h2>Step 2: Add Questions</h2>
                <p>Add questions to your quiz.</p>
                <form id="qb-add-question-form">
                    <label for="question-text">Question:</label>
                    <input type="text" id="question-text" name="question_text" required>
                    <br>
                    <button type="button" id="qb-add-question-button">Next</button>
                </form>
            </div>

            <div class="qb-step" id="qb-step-3" style="display: none;">
                <h2>Step 3: Add Options and Points</h2>
                <p>Add options and assign points to each option.</p>
                <form id="qb-add-options-form">
                    <label for="option-text">Option:</label>
                    <input type="text" id="option-text" name="option_text" required>
                    <br>
                    <label for="option-points">Points:</label>
                    <input type="number" id="option-points" name="option_points" required>
                    <br>
                    <button type="button" id="qb-add-options-button">Finish</button>
                </form>
            </div>

            <div id="qb-completion" style="display: none;">
                <h2>Setup Complete!</h2>
                <p>Your quiz is ready. You can now manage your quizzes from the admin menu.</p>
                <a href="<?php echo admin_url('admin.php?page=quiz-builder'); ?>" class="button button-primary">Go to Quiz Builder</a>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                let currentStep = 1;

                function showStep(step) {
                    document.querySelectorAll('.qb-step').forEach(el => el.style.display = 'none');
                    document.getElementById(`qb-step-${step}`).style.display = 'block';
                }

                document.getElementById('qb-create-quiz-button').addEventListener('click', function() {
                    // Simulate quiz creation
                    currentStep++;
                    showStep(currentStep);
                });

                document.getElementById('qb-add-question-button').addEventListener('click', function() {
                    // Simulate question addition
                    currentStep++;
                    showStep(currentStep);
                });

                document.getElementById('qb-add-options-button').addEventListener('click', function() {
                    // Simulate options addition
                    document.getElementById('qb-onboarding').style.display = 'none';                document.getElementById('qb-completion').style.display = 'block';
                });
            });
        </script>
    </div>
    <?php
}
