<?php
/**
 * Template for displaying quiz
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display quiz template
 */
function qb_get_quiz_display($quiz, $questions, $options, $settings) {    
    $output = '<div class="quiz-container" data-quiz-id="' . esc_attr($quiz->id) . '">';
    $output .= '<h2>' . esc_html($quiz->title) . '</h2>';
    
    if (!empty($quiz->description)) {
        $output .= '<div class="quiz-description">' . wp_kses_post($quiz->description) . '</div>';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce field is output for form security.
    $current_url = get_permalink();
    if (!$current_url) {
        // Safely get the current URL as fallback
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $current_url = !empty($request_uri) ? home_url($request_uri) : home_url();
    }
    $output .= '<form method="post" action="' . esc_url($current_url) . '" class="quiz-form" id="quiz-form-' . esc_attr($quiz->id) . '">';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce field is output for form security.
    $output .= wp_nonce_field('qb_quiz_submission', 'qb_quiz_nonce', true, false);
    $output .= '<input type="hidden" name="action" value="qb_handle_quiz_submission">';
    $output .= '<input type="hidden" name="quiz_id" value="' . esc_attr($quiz->id) . '">';    // Group questions into pages if pagination is enabled
    if ($settings->is_paginated) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $questions_per_page = max(1, intval($settings->questions_per_page));
        $total_pages = ceil(count($questions) / $questions_per_page);
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = isset($_GET['quiz_page']) ? max(1, min(intval($_GET['quiz_page']), $total_pages)) : 1;
        
        // Debug: Log pagination info
        
        $output .= '<div class="quiz-pagination-info">';
        $output .= '<span class="current-page">Page ' . esc_html($current_page) . ' of ' . esc_html($total_pages) . '</span>';
        $output .= '</div>';

        $start_index = ($current_page - 1) * $questions_per_page;
        $end_index = min($start_index + $questions_per_page, count($questions));
        
        $current_questions = array_slice($questions, $start_index, $questions_per_page);
        
        // Debug: Log current page questions
    } else {
        $current_questions = $questions;
    }

    $output .= '<div class="questions-container">';
    foreach ($current_questions as $question) {
        $required_attr = isset($question->required) && $question->required ? ' data-required="true"' : '';
        $output .= '<div class="question" data-question-id="' . esc_attr($question->id) . '"' . $required_attr . '>';
        
        $question_title = esc_html($question->question);
        // Note: Asterisk will be added dynamically when validation fails
        $output .= '<h3>' . $question_title . '</h3>';
        
        $question_options = array_filter($options, function($option) use ($question) {
            return $option->question_id == $question->id;
        });

        $output .= '<div class="options">';
        foreach ($question_options as $option) {
            $output .= '<label class="option">';
            $required_html = (isset($question->required) && $question->required) ? ' required' : '';
            $output .= '<input type="radio" name="question_' . esc_attr($question->id) . '" value="' . esc_attr($option->id) . '"' . $required_html . '>';
            $output .= '<span class="option-text">' . esc_html($option->option_text) . '</span>';
            $output .= '</label>';
        }
        $output .= '</div>';
        $output .= '</div>';
    }
    $output .= '</div>';

    // Add navigation buttons if pagination is enabled
    if ($settings->is_paginated) {
        $output .= '<div class="quiz-navigation">';        // Previous button
        if ($current_page > 1) {
            $current_url = remove_query_arg('quiz_page');
            $prev_url = add_query_arg('quiz_page', $current_page - 1, $current_url);
            $output .= '<a href="' . esc_url($prev_url) . '" class="button prev-button">Previous</a>';
        }
        
        // Next/Submit button
        if ($current_page < $total_pages) {
            $current_url = remove_query_arg('quiz_page');
            $next_url = add_query_arg('quiz_page', $current_page + 1, $current_url);            $output .= '<button type="button" class="button next-button" data-next-url="' . esc_attr($next_url) . '">Next</button>';
        } else {
            $output .= '<button type="button" class="button submit-button">Submit Quiz</button>';
        }
        
        $output .= '</div>';
    } else {
        $output .= '<button type="button" class="button submit-button">Submit Quiz</button>';
    }

    $output .= '</form>';
    $output .= '</div>';

    // Add JavaScript for handling answers
    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof jQuery === "undefined") {
            return;
        }

        (function($) {
            // Debug: Log all questions and their required status
            $(".question").each(function() {
                var $q = $(this);
                var qId = $q.data("question-id");
                var isRequired = $q.data("required");
                var hasRequiredInputs = $q.find("input[required]").length;

            });
            // Function to validate required questions on current page
            function validateCurrentPage() {
                var hasErrors = false;
                var errorMessages = [];
                
                // Clear previous error styling and messages
                $(".question").removeClass("error-required");
                $(".required-error-message").remove();
                $(".general-error-message").remove();
                
                // Remove any existing asterisks
                $(".question h3 .required-indicator").remove();
                
                // Count total required questions on this page
                var totalRequired = $(".question[data-required=\"true\"]").length;
                
                // Check each required question on current page
                $(".question[data-required=\"true\"]").each(function() {
                    var $question = $(this);
                    var questionId = $question.data("question-id");
                    var isAnswered = $question.find("input[type=\"radio\"]:checked").length > 0;
                    
                    
                    if (!isAnswered) {
                        hasErrors = true;
                        $question.addClass("error-required");                        // Add asterisk to question title
                        var $questionTitle = $question.find("h3");
                        if ($questionTitle.find(".required-indicator").length === 0) {
                            $questionTitle.append(\' <span class="required-indicator" style="color: #e74c3c; font-weight: bold;">*</span>\');
                        }
                        
                        // Add error message
                        var questionText = $question.find("h3").text().replace("*", "").trim();
                        var errorMsg = $("<div class=\"required-error-message\" style=\"color: #e74c3c; font-size: 14px; margin-top: 10px; padding: 10px; background: #ffeaea; border: 1px solid #e74c3c; border-radius: 4px;\">This question is required and must be answered before proceeding.</div>");
                        $question.append(errorMsg);
                        
                        errorMessages.push(questionText);
                    }
                });
                
                
                if (hasErrors) {
                    // Scroll to first error
                    var firstError = $(".question.error-required").first();
                    if (firstError.length) {
                        $("html, body").animate({
                            scrollTop: firstError.offset().top - 100
                        }, 500);
                    }
                    
                    // Show general error message
                    var generalError = $("<div class=\"general-error-message\" style=\"color: #e74c3c; font-size: 16px; font-weight: bold; margin: 20px 0; padding: 15px; background: #ffeaea; border: 1px solid #e74c3c; border-radius: 4px; text-align: center;\">Please answer all required questions (marked with *) before proceeding.</div>");
                    $(".quiz-container").prepend(generalError);
                }
                
                return !hasErrors;
            }// Handle Next button clicks (for paginated quizzes)
            $(".next-button").on("click", function(e) {
                e.preventDefault();
                
                if (validateCurrentPage()) {
                    // If validation passes, navigate to next page
                    var nextUrl = $(this).data("next-url");
                    window.location.href = nextUrl;
                }
                // If validation fails, the error messages will be shown
            });

            // Handle Submit button clicks (for final page or non-paginated quizzes)
            $(".submit-button").on("click", function(e) {
                e.preventDefault();
                
                
                if (!validateCurrentPage()) {
                    return false;
                }
                
                
                // Remove any existing error messages if validation passes
                $(".general-error-message").remove();
                
                // Add all saved answers to the form before submission
                addSavedAnswersToForm($(".quiz-form"));
                
                // Clear localStorage after adding answers to form
                localStorage.removeItem(storageKey);
                
                // Change the button type to submit and then trigger the form submission
                var $form = $(".quiz-form");
                var $submitButton = $(this);
                
                // Disable the button to prevent double submission
                $submitButton.prop("disabled", true).text("Submitting...");
                
                // Try multiple submission methods for better compatibility
                var submissionMethods = [
                    // Method 1: Create temporary submit button
                    function() {
                        var $tempSubmit = $("<input>")
                            .attr("type", "submit")
                            .attr("name", "quiz_submit")
                            .attr("value", "Submit")
                            .hide()
                            .appendTo($form);
                        
                        setTimeout(function() {
                            $tempSubmit[0].click();
                        }, 100);
                    },
                    
                    // Method 2: Direct form submission
                    function() {
                        setTimeout(function() {
                            $form[0].submit();
                        }, 500);
                    },
                    
                    // Method 3: AJAX fallback
                    function() {
                        setTimeout(function() {
                            submitViaAjax($form);
                        }, 1000);
                    }
                ];
                
                // Try the first method
                submissionMethods[0]();
                
                // Set up fallbacks
                setTimeout(function() {
                    if ($submitButton.prop("disabled")) {
                        submissionMethods[1]();
                    }
                }, 600);
                
                setTimeout(function() {
                    if ($submitButton.prop("disabled")) {
                        submissionMethods[2]();
                    }
                }, 1500);
            });
            
            // AJAX submission function
            function submitViaAjax($form) {
                
                var formData = new FormData($form[0]);
                formData.append("action", "qb_submit_quiz");
                
                $.ajax({
                    url: "' . esc_js(admin_url('admin-ajax.php')) . '",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success && response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            $(".submit-button").prop("disabled", false).text("Submit Quiz");
                            alert("There was an error submitting the quiz. Please try again.");
                        }
                    },
                    error: function(xhr, status, error) {
                        $(".submit-button").prop("disabled", false).text("Submit Quiz");
                        alert("There was an error submitting the quiz. Please try again.");
                    }
                });
            }            // Keep the form submit handler as a backup, but it should rarely be used now
            $(".quiz-form").on("submit", function(e) {
                
                // If this is triggered by our temporary submit button, allow it
                if ($(this).find("input[name=\"quiz_submit\"]").length > 0) {
                    return true;
                }
                
                // Otherwise prevent default and use our validation
                e.preventDefault();
                
                if (!validateCurrentPage()) {
                    return false;
                }
                
                // Add saved answers and allow submission
                addSavedAnswersToForm($(this));
                localStorage.removeItem(storageKey);
                
                // Re-trigger the submit
                return true;
            });
            
            // Clear error styling when user answers a required question
            $(document).on("change", ".question[data-required=\"true\"] input[type=\"radio\"]", function() {
                var $question = $(this).closest(".question");
                $question.removeClass("error-required");
                $question.find(".required-error-message").remove();
                
                // Check if all required questions are now answered
                var unansweredRequired = $(".question[data-required=\"true\"]:not(:has(input[type=\"radio\"]:checked))").length;
                if (unansweredRequired === 0) {
                    $(".general-error-message").remove();
                }
            });
            
            
            // Get quiz ID and create storage key
            var quizId = "' . esc_js($quiz->id) . '";
            var storageKey = "quiz_" + quizId + "_answers";
            
            
            // Save answers to localStorage
            function saveAnswer(questionId, optionId) {
                try {
                    var answers = JSON.parse(localStorage.getItem(storageKey) || "{}");
                    answers[questionId] = optionId;
                    localStorage.setItem(storageKey, JSON.stringify(answers));
                } catch (e) {
                }
            }
            
            // Load answers from localStorage
            function loadAnswers() {
                try {
                    var answers = JSON.parse(localStorage.getItem(storageKey) || "{}");
                    
                    Object.keys(answers).forEach(function(questionId) {
                        var optionId = answers[questionId];
                        var input = document.querySelector(\'input[name="question_\' + questionId + \'"][value="\' + optionId + \'"]\');
                        if (input) {
                            input.checked = true;
                        }
                    });
                } catch (e) {
                }
            }

            // Add hidden inputs for all saved answers
            function addSavedAnswersToForm($form) {
                try {
                    var answers = JSON.parse(localStorage.getItem(storageKey) || "{}");
                    
                    // Remove any existing hidden inputs for answers
                    $form.find(".saved-answer-input").remove();
                    
                    // Add hidden inputs for each saved answer
                    Object.entries(answers).forEach(function([questionId, optionId]) {
                        var hiddenInput = $("<input>")
                            .attr("type", "hidden")
                            .attr("class", "saved-answer-input")
                            .attr("name", "question_" + questionId)
                            .attr("value", optionId);
                        $form.append(hiddenInput);
                    });
                } catch (e) {
                }
            }
            
            // Handle radio button changes
            $(document).on("change", ".question input[type=radio]", function() {
                var questionId = $(this).closest(".question").data("question-id");
                var optionId = $(this).val();
                saveAnswer(questionId, optionId);            });
              // Load saved answers when page loads
            loadAnswers();
              // Handle Previous button navigation (no validation needed)
            $(".prev-button").click(function() {
                // Previous navigation does not need validation, so it works as normal
            });
            
        })(jQuery);
    });
    </script>';

    // Add styles
    $output .= '<style>
    .quiz-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .quiz-description {
        margin-bottom: 20px;
        color: #666;
    }
    .question {
        margin-bottom: 30px;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 5px;
    }
    .options {
        margin-top: 15px;
    }
    .option {
        display: block;
        margin: 10px 0;
        padding: 10px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
    }
    .option:hover {
        background: #f0f0f0;
    }
    .option input[type="radio"] {
        margin-right: 10px;
    }
    .quiz-navigation {
        margin-top: 30px;
        display: flex;
        justify-content: space-between;
    }    .quiz-pagination-info {
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
    }
    .required-indicator {
        color: #e74c3c;
        font-weight: bold;
        margin-left: 5px;
    }
    .question.error-required {
        border: 2px solid #e74c3c;
        background: #ffeaea;
    }
    .question.error-required h3 {
        color: #e74c3c;
    }
    .general-error-message {
        color: #e74c3c;
        font-size: 16px;
        font-weight: bold;
        margin: 20px 0;
        padding: 15px;
        background: #ffeaea;
        border: 1px solid #e74c3c;
        border-radius: 4px;
        text-align: center;
    }
    .required-error-message {
        color: #e74c3c;
        font-size: 14px;
        margin-top: 10px;
        padding: 10px;
        background: #ffeaea;
        border: 1px solid #e74c3c;
        border-radius: 4px;
    }
    </style>';

    return $output;
}