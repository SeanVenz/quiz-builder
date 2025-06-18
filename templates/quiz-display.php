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

    $output .= '<form method="post" class="quiz-form" id="quiz-form-' . esc_attr($quiz->id) . '">';
    $output .= wp_nonce_field('qb_quiz_submission', 'qb_quiz_nonce', true, false);
    $output .= '<input type="hidden" name="action" value="qb_handle_quiz_submission">';
    $output .= '<input type="hidden" name="quiz_id" value="' . esc_attr($quiz->id) . '">';    // Group questions into pages if pagination is enabled
    if ($settings->is_paginated) {
        $questions_per_page = max(1, intval($settings->questions_per_page));
        $total_pages = ceil(count($questions) / $questions_per_page);
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
            console.error("jQuery is not loaded!");
            return;
        }

        (function($) {
            // Debug: Log all questions and their required status
            console.log("=== QUIZ DEBUG INFO ===");
            $(".question").each(function() {
                var $q = $(this);
                var qId = $q.data("question-id");
                var isRequired = $q.data("required");
                var hasRequiredInputs = $q.find("input[required]").length;
                console.log("Question " + qId + ":", {
                    "data-required": isRequired,
                    "has required inputs": hasRequiredInputs,
                    "question element": $q[0]
                });
            });
            console.log("=== END DEBUG INFO ===");            // Function to validate required questions on current page
            function validateCurrentPage() {
                console.log("=== VALIDATION START ===");
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
                console.log("Total required questions on this page:", totalRequired);
                
                // Check each required question on current page
                $(".question[data-required=\"true\"]").each(function() {
                    var $question = $(this);
                    var questionId = $question.data("question-id");
                    var isAnswered = $question.find("input[type=\"radio\"]:checked").length > 0;
                    
                    console.log("Question " + questionId + ": required=true, answered=" + isAnswered);
                    
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
                        console.log("Added error for question " + questionId);
                    }
                });
                
                console.log("Validation result: hasErrors=" + hasErrors + ", errorCount=" + errorMessages.length);
                
                if (hasErrors) {
                    // Scroll to first error
                    var firstError = $(".question.error-required").first();
                    if (firstError.length) {
                        console.log("Scrolling to first error");
                        $("html, body").animate({
                            scrollTop: firstError.offset().top - 100
                        }, 500);
                    }
                    
                    // Show general error message
                    var generalError = $("<div class=\"general-error-message\" style=\"color: #e74c3c; font-size: 16px; font-weight: bold; margin: 20px 0; padding: 15px; background: #ffeaea; border: 1px solid #e74c3c; border-radius: 4px; text-align: center;\">Please answer all required questions (marked with *) before proceeding.</div>");
                    $(".quiz-container").prepend(generalError);
                    console.log("Added general error message");
                }
                
                console.log("=== VALIDATION END ===");
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
                
                console.log("Submit button clicked, validating...");
                
                if (!validateCurrentPage()) {
                    console.log("Validation failed, preventing submission");
                    return false;
                }
                
                console.log("Validation passed, preparing form submission");
                
                // Remove any existing error messages if validation passes
                $(".general-error-message").remove();
                
                // Add all saved answers to the form before submission
                addSavedAnswersToForm($(".quiz-form"));
                console.log("Form submitting with all saved answers");
                
                // Clear localStorage after adding answers to form
                localStorage.removeItem(storageKey);
                
                // Submit the form programmatically
                $(".quiz-form")[0].submit();
            });            // Keep the form submit handler as a backup, but it should rarely be used now
            $(".quiz-form").on("submit", function(e) {
                console.log("Form submit event triggered (backup handler)");
                // This should not normally run since we are using button click handlers now
                // But keeping it as a safety net
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
            
            // Debug check
            console.log("Quiz script initialized");
            
            // Get quiz ID and create storage key
            var quizId = "' . esc_js($quiz->id) . '";
            var storageKey = "quiz_" + quizId + "_answers";
            
            console.log("Quiz ID:", quizId);
            console.log("Storage key:", storageKey);
            
            // Save answers to localStorage
            function saveAnswer(questionId, optionId) {
                try {
                    var answers = JSON.parse(localStorage.getItem(storageKey) || "{}");
                    answers[questionId] = optionId;
                    localStorage.setItem(storageKey, JSON.stringify(answers));
                    console.log("Saved answer:", questionId, optionId);
                    console.log("Current answers:", answers);
                } catch (e) {
                    console.error("Error saving answer:", e);
                }
            }
            
            // Load answers from localStorage
            function loadAnswers() {
                try {
                    var answers = JSON.parse(localStorage.getItem(storageKey) || "{}");
                    console.log("Loading answers:", answers);
                    
                    Object.keys(answers).forEach(function(questionId) {
                        var optionId = answers[questionId];
                        var input = document.querySelector(\'input[name="question_\' + questionId + \'"][value="\' + optionId + \'"]\');
                        if (input) {
                            input.checked = true;
                            console.log("Restored answer for question:", questionId);
                        }
                    });
                } catch (e) {
                    console.error("Error loading answers:", e);
                }
            }

            // Add hidden inputs for all saved answers
            function addSavedAnswersToForm($form) {
                try {
                    var answers = JSON.parse(localStorage.getItem(storageKey) || "{}");
                    console.log("Adding saved answers to form:", answers);
                    
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
                        console.log("Added hidden input for question:", questionId, "option:", optionId);
                    });
                } catch (e) {
                    console.error("Error adding saved answers to form:", e);
                }
            }
            
            // Handle radio button changes
            $(document).on("change", ".question input[type=radio]", function() {
                var questionId = $(this).closest(".question").data("question-id");
                var optionId = $(this).val();
                console.log("Option changed - Question:", questionId, "Option:", optionId);
                saveAnswer(questionId, optionId);            });
              // Load saved answers when page loads
            loadAnswers();
              // Handle Previous button navigation (no validation needed)
            $(".prev-button").click(function() {
                console.log("Previous button clicked, answers saved in localStorage");
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