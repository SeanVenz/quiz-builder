<div class="wrap">
    <div class="qb-welcome-header">
        <div class="qb-welcome-icon">🧠</div>
        <h1>Welcome to Quiz Builder</h1>
        <p class="qb-welcome-subtitle">The Complete WordPress Quiz Solution</p>
    </div>

    <div class="qb-plugin-overview">
        <div class="qb-overview-content">
            <h2>🎯 What is Quiz Builder?</h2>
            <p>Quiz Builder is a powerful, lightweight WordPress plugin that lets you create engaging quizzes for your website visitors. Whether you're an educator, blogger, or business owner, our plugin helps you:</p>
            
            <div class="qb-features-grid">
                <div class="qb-feature">
                    <div class="qb-feature-icon">📝</div>
                    <h3>Create Interactive Quizzes</h3>
                    <p>Build unlimited quizzes with multiple-choice questions and custom point values</p>
                </div>
                <div class="qb-feature">
                    <div class="qb-feature-icon">📊</div>
                    <h3>Track Performance</h3>
                    <p>Monitor quiz attempts, scores, and user engagement with detailed analytics</p>
                </div>
                <div class="qb-feature">
                    <div class="qb-feature-icon">⚙️</div>
                    <h3>Flexible Display Options</h3>
                    <p>Customize quiz appearance, enable pagination, and control result displays</p>
                </div>
                <div class="qb-feature">
                    <div class="qb-feature-icon">🚀</div>
                    <h3>Easy Integration</h3>
                    <p>Add quizzes anywhere with simple shortcodes or WordPress blocks</p>
                </div>
            </div>

            <div class="qb-use-cases">
                <h3>💡 Perfect For:</h3>
                <div class="qb-use-case-list">
                    <span class="qb-use-case">🎓 Educational Websites</span>
                    <span class="qb-use-case">📈 Lead Generation</span>
                    <span class="qb-use-case">🎮 Entertainment Sites</span>
                    <span class="qb-use-case">💼 Employee Training</span>
                    <span class="qb-use-case">🧪 Product Recommendations</span>
                    <span class="qb-use-case">📝 Content Engagement</span>
                </div>
            </div>

            <div class="qb-setup-intro">
                <h3>🛠️ Let's Get Started!</h3>
                <p>We'll walk you through creating your first quiz in just 3 simple steps. It takes less than 5 minutes, and you'll have a fully functional quiz ready to engage your visitors!</p>
            </div>
        </div>
    </div>

    <div id="qb-onboarding">
        <div class="qb-step" id="qb-step-1">
            <div class="qb-step-header">
                <span class="qb-step-number">1</span>
                <div class="qb-step-title">
                    <h2>Create Your First Quiz</h2>
                    <p>Choose a compelling title and description that will attract your audience</p>
                </div>
            </div>            <form id="qb-create-quiz-form" class="qb-onboarding-form">
                <?php wp_nonce_field('qb_onboarding_quiz', 'qb_onboarding_nonce'); ?>
                <div class="qb-form-field">
                    <label for="quiz-title">Quiz Title</label>
                    <input type="text" id="quiz-title" name="quiz_title" class="regular-text" required placeholder="e.g., 'How Well Do You Know...?' or 'Test Your Knowledge of...'">
                    <p class="description">Make it engaging and descriptive to attract more participants</p>
                </div>
                <div class="qb-form-field">
                    <label for="quiz-description">Description (Optional)</label>
                    <textarea id="quiz-description" name="quiz_description" class="large-text" rows="3" placeholder="Briefly describe what this quiz covers and what participants can expect..."></textarea>
                    <p class="description">Help users understand what to expect from your quiz</p>
                </div>
                <button type="button" id="qb-create-quiz-button" class="button button-primary qb-primary-btn">Create Quiz & Continue</button>
            </form>
        </div>        <div class="qb-step" id="qb-step-2" style="display: none;">
            <div class="qb-step-header">
                <span class="qb-step-number">2</span>
                <div class="qb-step-title">
                    <h2>Add Your Questions</h2>
                    <p>Create engaging questions that will challenge and educate your users</p>
                </div>
            </div>
            
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
        </div>        <div class="qb-step" id="qb-step-3" style="display: none;">
            <div class="qb-step-header">
                <span class="qb-step-number">3</span>
                <div class="qb-step-title">
                    <h2>Configure Answer Options</h2>
                    <p>Set up multiple choice answers and assign point values to create scoring logic</p>
                </div>
            </div>
            
            <div id="options-step-content">
                <!-- Options sections will be dynamically generated for each question -->
            </div>
            
            <button type="button" id="qb-finish-onboarding" class="button button-primary">Finish Setup</button>
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
                <h3>🎉 What you've accomplished:</h3>
                <ul>
                    <li>✅ Created your first interactive quiz</li>
                    <li>✅ Added engaging questions for your audience</li>
                    <li>✅ Configured answer options with smart scoring</li>
                    <li>✅ Your quiz is live and ready to collect responses!</li>
                </ul>
            </div>

            <div class="qb-what-now">
                <h3>🚀 What happens now?</h3>
                <p>Your quiz is automatically saved and ready to use! You can:</p>
                <ul>
                    <li>📋 <strong>Embed it anywhere</strong> using the shortcode below</li>
                    <li>📊 <strong>View quiz attempts</strong> and analyze user responses</li>
                    <li>⚙️ <strong>Customize settings</strong> like pagination and result displays</li>
                    <li>➕ <strong>Create more quizzes</strong> from your dashboard</li>
                </ul>
            </div>

            <div class="shortcode-section">
                <h3>🚀 How to Display Your Quiz</h3>
                <p style="margin-bottom: 15px; opacity: 0.9;">Copy and paste this shortcode into any post or page:</p>
                
                <div class="shortcode-display">
                    <span id="quiz-shortcode">[quiz_builder quiz_id="<span id="shortcode-quiz-id">1</span>"]</span>
                </div>
                <button type="button" id="copy-shortcode" class="copy-button">📋 Copy Shortcode</button>
                
                <h4 style="margin-top: 25px; margin-bottom: 10px;">Or use in PHP templates:</h4>
                <div class="php-example">
                    <span id="php-code">&lt;?php echo do_shortcode('[quiz_builder quiz_id="<span id="php-quiz-id">1</span>"]'); ?&gt;</span>
                </div>
                <button type="button" id="copy-php" class="copy-button">📋 Copy PHP Code</button>
            </div>

            <div class="results-section">
                <h3>📊 How to Display Results</h3>
                <p style="margin-bottom: 15px; opacity: 0.9;"><strong>Step 1:</strong> Create a new page in your WordPress admin called "Quiz Results"</p>
                <p style="margin-bottom: 15px; opacity: 0.9;"><strong>Step 2:</strong> Add the shortcode below to that page:</p>
                
                <h4 style="margin-bottom: 10px;">For WordPress Gutenberg Editor:</h4>
                <div class="shortcode-display">
                    <span id="results-shortcode">[quiz_results]</span>
                </div>
                <button type="button" id="copy-results-shortcode" class="copy-button">📋 Copy Results Shortcode</button>
                
                <h4 style="margin-top: 25px; margin-bottom: 10px;">For custom PHP themes:</h4>
                <div class="php-example">
                    <span id="results-php-code">&lt;?php echo do_shortcode('[quiz_results]'); ?&gt;</span>
                </div>
                <button type="button" id="copy-results-php" class="copy-button">📋 Copy Results PHP Code</button>
                
                <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 15px; margin: 15px 0;">
                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">
                        💡 <strong>Important:</strong> You need to manually create a page called "Quiz Results" and add the <code>[quiz_results]</code> shortcode to it. This page will display the results when users complete your quiz.
                    </p>
                </div>
            </div>
              <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 20px; margin: 20px 0; backdrop-filter: blur(10px);">
                <h3 style="color: #fff; margin-top: 0;">💡 Pro Tips for Success</h3>
                <div style="margin-bottom: 15px; line-height: 1.6;">
                    <p>🎯 <strong>Engagement Tip:</strong> Keep questions clear and concise for better completion rates</p>
                    <p>📈 <strong>Analytics Tip:</strong> Monitor your quiz attempts to understand user behavior</p>
                    <p>🎨 <strong>Customization Tip:</strong> Use the Settings page to customize quiz appearance and functionality</p>
                    <p>🔄 <strong>Growth Tip:</strong> Create multiple quizzes to keep your content fresh and engaging</p>
                </div>
            </div>
            
            <div class="completion-actions">
                <button type="button" id="qb-go-to-dashboard" class="dashboard-button">Go to Dashboard</button>
            </div>
        </div>
    </div>
</div>
