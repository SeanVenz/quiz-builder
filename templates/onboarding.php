<div class="wrap">
    <h1>Welcome to Quiz Builder</h1>
    <p>Thank you for installing Quiz Builder! Let's create your first quiz to get started.</p>

    <div id="qb-onboarding">
        <div class="qb-step" id="qb-step-1">
            <h2>Step 1: Create a Quiz</h2>
            <p>Enter the title and description for your first quiz.</p>
            <form id="qb-create-quiz-form">
                <?php wp_nonce_field('qb_onboarding_quiz', 'qb_onboarding_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Quiz Title</th>
                        <td><input type="text" id="quiz-title" name="quiz_title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Description</th>
                        <td><textarea id="quiz-description" name="quiz_description" class="large-text" rows="3"></textarea></td>
                    </tr>
                </table>
                <button type="button" id="qb-create-quiz-button" class="button button-primary">Create Quiz & Continue</button>
            </form>
        </div>

        <div class="qb-step" id="qb-step-2" style="display: none;">
            <h2>Step 2: Add Questions</h2>
            <p>Add one or more questions to your quiz. You can add as many as you need!</p>
            
            <div id="questions-container" class="questions-container">
                <div class="question-row">
                    <div class="question-header">
                        <span class="question-label">Question 1</span>
                        <div class="question-actions">
                            <!-- First question can't be removed -->
                        </div>
                    </div>
                    <input type="text" name="question_text[]" class="question-input large-text" placeholder="Enter your question" required>
                </div>
            </div>
            
            <button type="button" id="add-question-btn" class="button add-question-btn">Add Another Question</button>
            <br><br>
            <button type="button" id="qb-add-questions-button" class="button button-primary">Add Questions & Continue</button>
        </div>

        <div class="qb-step" id="qb-step-3" style="display: none;">
            <h2>Step 3: Add Options and Points</h2>
            <p>Add multiple options for each of your questions.</p>
            
            <div id="options-step-content">
                <!-- Options sections will be dynamically generated for each question -->
            </div>
            
            <button type="button" id="qb-finish-onboarding" class="button button-primary">Finish Setup</button>
            <button type="button" id="test-completion" class="button" style="margin-left: 10px; background: #f0ad4e; border-color: #eea236; color: white;">Test Completion Screen</button>
        </div>
    </div>

    <!-- Completion screen - moved outside of onboarding container -->
    <div id="qb-completion" style="display: none;">
        <div class="completion-container">
            <div class="completion-header">
                <div class="completion-icon">🎉</div>
                <h1 class="completion-title">Congratulations!</h1>
                <p class="completion-subtitle">Your quiz has been created successfully!</p>
            </div>
            
            <div class="accomplishments">
                <h3>What you've accomplished:</h3>
                <ul>
                    <li>✅ Created your first quiz</li>
                    <li>✅ Added questions to engage your audience</li>
                    <li>✅ Set up options with point values</li>
                    <li>✅ Your quiz is ready to use!</li>
                </ul>
            </div>

            <div class="shortcode-section">
                <h3>🚀 How to Display Your Quiz</h3>
                <p style="margin-bottom: 15px; opacity: 0.9;">Copy and paste this shortcode into any post or page:</p>
                
                <div class="shortcode-display">
                    <span id="quiz-shortcode">[quiz_builder id="<span id="shortcode-quiz-id">1</span>"]</span>
                </div>
                <button type="button" id="copy-shortcode" class="copy-button">📋 Copy Shortcode</button>
                
                <h4 style="margin-top: 25px; margin-bottom: 10px;">Or use in PHP templates:</h4>
                <div class="php-example">
                    <span id="php-code">&lt;?php echo do_shortcode('[quiz_builder id="<span id="php-quiz-id">1</span>"]'); ?&gt;</span>
                </div>
                <button type="button" id="copy-php" class="copy-button">📋 Copy PHP Code</button>
            </div>
            
            <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 20px; margin: 20px 0; backdrop-filter: blur(10px);">
                <h3 style="color: #fff; margin-top: 0;">💡 Next Steps</h3>
                <p style="margin-bottom: 15px; line-height: 1.6;">
                    🎯 <strong>Pro Tip:</strong> You can create more quizzes, manage questions, and view quiz attempts from your dashboard. Your quiz is automatically saved and ready to use!
                </p>
            </div>
            
            <div class="completion-actions">
                <button type="button" id="qb-go-to-dashboard" class="dashboard-button">Go to Dashboard</button>
            </div>
        </div>
    </div>
</div>
