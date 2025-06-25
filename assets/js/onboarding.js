jQuery(document).ready(function($) {
    console.log('Onboarding script loaded');
    
    // Check if required elements exist
    if (!$('#qb-onboarding').length) {
        console.error('Onboarding container not found');
        return;
    }
    if (!$('#qb-completion').length) {
        console.error('Completion container not found');
        return;
    }
    
    let currentStep = 1;
    let createdQuizId = null;
    let createdQuestions = []; // Array to store multiple questions

    function showStep(step) {
        $('.qb-step').hide();
        $('#qb-step-' + step).show();
    }

    // Step 1: Create Quiz
    $('#qb-create-quiz-button').click(function() {
        const title = $('#quiz-title').val();
        const description = $('#quiz-description').val();
        
        if (!title) {
            alert('Please enter a quiz title');
            return;
        }        // Disable the button to prevent double-clicks
        $('#qb-create-quiz-button').prop('disabled', true).text('Creating quiz...');
        
        $.ajax({
            url: qb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'qb_onboarding_create_quiz',
                quiz_title: title,
                quiz_description: description,
                nonce: qb_ajax.nonce
            },
            success: function(response) {
                console.log('Quiz creation response:', response);
                if (response.success) {
                    createdQuizId = response.data.quiz_id;
                    
                    // Show success message
                    const successMsg = $('<div class="notice notice-success" style="margin: 10px 0; padding: 10px;"><p><strong>✅ Quiz created successfully!</strong> Quiz ID: ' + createdQuizId + '</p></div>');
                    $('#qb-step-1').prepend(successMsg);
                    
                    // Move to next step after a brief delay
                    setTimeout(function() {
                        currentStep++;
                        showStep(currentStep);
                    }, 1500);
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $('#qb-create-quiz-button').prop('disabled', false).text('Create Quiz & Continue');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                alert('Error: Failed to create quiz. Please try again.');
                $('#qb-create-quiz-button').prop('disabled', false).text('Create Quiz & Continue');
            }
        });
    });

    // Add question functionality
    $('#add-question-btn').click(function() {
        const questionCount = $('#questions-container .question-row').length + 1;
        const newQuestion = `
            <div class="question-row">
                <div class="question-header">
                    <span class="question-label">Question ${questionCount}</span>
                    <div class="question-actions">
                        <button type="button" class="remove-question">Remove</button>
                    </div>
                </div>
                <input type="text" name="question_text[]" class="question-input large-text" placeholder="Enter your question" required>
            </div>
        `;
        $('#questions-container').append(newQuestion);
    });

    // Remove question functionality
    $(document).on('click', '.remove-question', function() {
        if ($('#questions-container .question-row').length > 1) {
            $(this).closest('.question-row').remove();
            // Renumber questions
            $('#questions-container .question-row').each(function(index) {
                $(this).find('.question-label').text('Question ' + (index + 1));
            });
        } else {
            alert('You must have at least one question.');
        }
    });

    // Step 2: Add Questions
    $('#qb-add-questions-button').click(function() {
        const questions = [];
        let isValid = true;
        
        $('#questions-container .question-row').each(function() {
            const questionText = $(this).find('input[name="question_text[]"]').val().trim();
            if (!questionText) {
                isValid = false;
                $(this).find('input[name="question_text[]"]').addClass('error').css('border-color', '#dc3232');
            } else {
                $(this).find('input[name="question_text[]"]').removeClass('error').css('border-color', '#ddd');
                questions.push(questionText);
            }
        });

        if (!isValid) {
            alert('Please fill in all question fields');
            return;
        }

        // Validate we have questions before sending
        if (questions.length === 0) {
            alert('Please add at least one question');
            return;
        }

        // Validate each question is not empty
        const validQuestions = questions.filter(q => q && q.trim().length > 0);
        if (validQuestions.length === 0) {
            alert('Please add at least one valid question');
            return;
        }

        // Disable the button to prevent double-clicks
        $('#qb-add-questions-button').prop('disabled', true).text('Adding questions...');        // Send all questions in a single AJAX request
        $.ajax({
            url: qb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'qb_onboarding_add_questions',
                quiz_id: createdQuizId,
                questions: questions,
                nonce: qb_ajax.nonce
            },
            success: function(response) {
                console.log('Questions response:', response);
                if (response.success) {
                    createdQuestions = response.data.question_ids;
                    
                    // Show success message
                    const successMsg = $('<div class="notice notice-success" style="margin: 10px 0; padding: 10px;"><p><strong>✅ Questions added successfully!</strong> Added ' + questions.length + ' question(s)</p></div>');
                    $('#qb-step-2').prepend(successMsg);
                    
                    // Move to next step after a brief delay
                    setTimeout(function() {
                        currentStep++;
                        showStep(currentStep);
                        // Setup options for the first question
                        setupOptionsForAllQuestions();
                    }, 1500);
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $('#qb-add-questions-button').prop('disabled', false).text('Add Questions & Continue');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                alert('Error: Failed to add questions. Please try again.');
                $('#qb-add-questions-button').prop('disabled', false).text('Add Questions & Continue');
            }
        });
    });

    // Setup options sections for all questions
    function setupOptionsForAllQuestions() {
        $('#options-step-content').empty();
        
        createdQuestions.forEach(function(questionId, index) {
            const questionText = $('#questions-container .question-row').eq(index).find('input[name="question_text[]"]').val();
            const optionsSection = `
                <div class="question-options-section" data-question-id="${questionId}">
                    <h3>Options for: "${questionText}"</h3>
                    <div class="options-container">
                        <div class="option-row">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Option 1</th>
                                    <td>
                                        <input type="text" name="option_text_${questionId}[]" class="regular-text" placeholder="Option text" required>
                                        <input type="number" name="option_points_${questionId}[]" class="small-text" placeholder="Points" required>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <button type="button" class="button add-option-btn" data-question-id="${questionId}">Add Another Option</button>
                    <hr style="margin: 20px 0;">
                </div>
            `;
            $('#options-step-content').append(optionsSection);
        });
    }

    // Add option functionality (updated for multiple questions)
    $(document).on('click', '.add-option-btn', function() {
        const questionId = $(this).data('question-id');
        const optionsContainer = $(this).siblings('.options-container');
        const optionCount = optionsContainer.find('.option-row').length + 1;
        
        const newOption = `
            <div class="option-row">
                <table class="form-table">
                    <tr>
                        <th scope="row">Option ${optionCount}</th>
                        <td>
                            <input type="text" name="option_text_${questionId}[]" class="regular-text" placeholder="Option text" required>
                            <input type="number" name="option_points_${questionId}[]" class="small-text" placeholder="Points" required>
                            <button type="button" class="button remove-option">Remove</button>
                        </td>
                    </tr>
                </table>
            </div>
        `;
        optionsContainer.append(newOption);
    });

    // Remove option functionality
    $(document).on('click', '.remove-option', function() {
        const optionsContainer = $(this).closest('.options-container');
        if (optionsContainer.find('.option-row').length > 1) {
            $(this).closest('.option-row').remove();
            // Renumber options
            optionsContainer.find('.option-row').each(function(index) {
                $(this).find('th').text('Option ' + (index + 1));
            });
        } else {
            alert('Each question must have at least one option.');
        }
    });

    // Step 3: Add Options
    $('#qb-finish-onboarding').click(function() {
        const allQuestionOptions = {};
        let isValid = true;
        
        $('.question-options-section').each(function() {
            const questionId = $(this).data('question-id');
            const options = [];
            
            $(this).find('.option-row').each(function() {
                const text = $(this).find('input[name="option_text_' + questionId + '[]"]').val();
                const points = $(this).find('input[name="option_points_' + questionId + '[]"]').val();
                if (text && points) {
                    options.push({text: text, points: points});
                } else {
                    isValid = false;
                }
            });
            
            if (options.length < 2) {
                isValid = false;
                alert('Each question must have at least 2 options');
                return false;
            }
            
            allQuestionOptions[questionId] = options;
        });

        if (!isValid) {
            alert('Please fill in all option fields and ensure each question has at least 2 options');
            return;
        }

        // Disable the button to prevent double-clicks
        $('#qb-finish-onboarding').prop('disabled', true).text('Adding options...');        $.ajax({
            url: qb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'qb_onboarding_add_all_options',
                question_options: allQuestionOptions,
                nonce: qb_ajax.nonce
            },
            success: function(response) {
                console.log('Options response:', response);
                if (response.success) {
                    // Show success message
                    const successMsg = $('<div class="notice notice-success" style="margin: 10px 0; padding: 10px;"><p><strong>✅ Options added successfully!</strong> Your quiz is now complete!</p></div>');
                    $('#qb-step-3').prepend(successMsg);
                    
                    console.log('About to show completion screen. Quiz ID:', createdQuizId);
                    
                    // Move to completion after a brief delay
                    setTimeout(function() {
                        console.log('Hiding onboarding and showing completion');
                        $('#qb-onboarding').hide();
                        
                        // Populate the quiz ID in the completion message
                        $('#shortcode-quiz-id').text(createdQuizId);
                        $('#php-quiz-id').text(createdQuizId);
                        
                        console.log('Showing completion screen');
                        $('#qb-completion').fadeIn(500);
                    }, 1500);
                } else {
                    console.error('Options creation failed:', response);
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $('#qb-finish-onboarding').prop('disabled', false).text('Finish Setup');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                alert('Error: Failed to add options. Please try again.');
                $('#qb-finish-onboarding').prop('disabled', false).text('Finish Setup');
            }
        });
    });    // Go to dashboard
    $('#qb-go-to-dashboard').click(function() {
        console.log('Go to dashboard button clicked');
        // Add a small delay to ensure user sees the action
        $(this).text('Loading Dashboard...').prop('disabled', true);
        
        // Mark onboarding as completed and redirect to dashboard
        $.ajax({
            url: qb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'qb_complete_onboarding',
                nonce: qb_ajax.nonce
            },
            success: function(response) {
                console.log('Onboarding completion response:', response);
                if (response.success && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    // Fallback to dashboard
                    window.location.href = qb_ajax.admin_url + 'admin.php?page=quiz-builder';
                }
            },
            error: function(xhr, status, error) {
                console.error('Error completing onboarding:', error);
                // Fallback to dashboard even on error
                window.location.href = qb_ajax.admin_url + 'admin.php?page=quiz-builder';
            }
        });
    });

    // Test completion screen (for debugging)
    $('#test-completion').click(function() {
        console.log('Test completion button clicked');
        if (!createdQuizId) {
            createdQuizId = 1; // Default for testing
            console.log('Using default quiz ID for testing:', createdQuizId);
        }
        console.log('Hiding onboarding and showing completion (test)');
        $('#qb-onboarding').hide();
        $('#shortcode-quiz-id').text(createdQuizId);
        $('#php-quiz-id').text(createdQuizId);
        $('#qb-completion').fadeIn(500);
    });

    // Copy shortcode functionality
    $('#copy-shortcode').click(function() {
        const shortcodeText = $('#quiz-shortcode').text();
        
        // Try modern clipboard API first, fall back to legacy method
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(shortcodeText).then(function() {
                showCopySuccess('#copy-shortcode', 'Copied!');
            }).catch(function() {
                fallbackCopyTextToClipboard(shortcodeText, '#copy-shortcode');
            });
        } else {
            fallbackCopyTextToClipboard(shortcodeText, '#copy-shortcode');
        }
    });

    // Copy PHP code functionality
    $('#copy-php').click(function() {
        const phpText = $('#php-code').text();
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(phpText).then(function() {
                showCopySuccess('#copy-php', 'Copied!');
            }).catch(function() {
                fallbackCopyTextToClipboard(phpText, '#copy-php');
            });
        } else {
            fallbackCopyTextToClipboard(phpText, '#copy-php');
        }
    });

    // Copy results shortcode functionality
    $('#copy-results-shortcode').click(function() {
        const resultsShortcodeText = $('#results-shortcode').text();
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(resultsShortcodeText).then(function() {
                showCopySuccess('#copy-results-shortcode', 'Copied!');
            }).catch(function() {
                fallbackCopyTextToClipboard(resultsShortcodeText, '#copy-results-shortcode');
            });
        } else {
            fallbackCopyTextToClipboard(resultsShortcodeText, '#copy-results-shortcode');
        }
    });

    // Copy results PHP code functionality
    $('#copy-results-php').click(function() {
        const resultsPhpText = $('#results-php-code').text();
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(resultsPhpText).then(function() {
                showCopySuccess('#copy-results-php', 'Copied!');
            }).catch(function() {
                fallbackCopyTextToClipboard(resultsPhpText, '#copy-results-php');
            });
        } else {
            fallbackCopyTextToClipboard(resultsPhpText, '#copy-results-php');
        }
    });

    // Fallback copy method for older browsers
    function fallbackCopyTextToClipboard(text, buttonSelector) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";
        
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(buttonSelector, 'Copied!');
            } else {
                showCopySuccess(buttonSelector, 'Copy failed');
            }
        } catch (err) {
            console.error('Fallback copy failed:', err);
            showCopySuccess(buttonSelector, 'Copy failed');
        }
        
        document.body.removeChild(textArea);
    }

    // Show copy success feedback
    function showCopySuccess(buttonSelector, message) {
        const $button = $(buttonSelector);
        const originalText = $button.text();
        
        $button.addClass('copy-success').text(message);
        
        setTimeout(function() {
            $button.removeClass('copy-success').text(originalText);
        }, 2000);
    }
});
