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
    $output .= '<input type="hidden" name="quiz_id" value="' . esc_attr($quiz->id) . '">';

    // Group questions into pages if pagination is enabled
    if ($settings->is_paginated) {
        $questions_per_page = max(1, intval($settings->questions_per_page));
        $total_pages = ceil(count($questions) / $questions_per_page);
        $current_page = isset($_GET['page']) ? max(1, min(intval($_GET['page']), $total_pages)) : 1;
        
        $output .= '<div class="quiz-pagination-info">';
        $output .= '<span class="current-page">Page ' . esc_html($current_page) . ' of ' . esc_html($total_pages) . '</span>';
        $output .= '</div>';

        $start_index = ($current_page - 1) * $questions_per_page;
        $end_index = min($start_index + $questions_per_page, count($questions));
        
        $current_questions = array_slice($questions, $start_index, $questions_per_page);
    } else {
        $current_questions = $questions;
    }

    $output .= '<div class="questions-container">';
    foreach ($current_questions as $question) {
        $output .= '<div class="question" data-question-id="' . esc_attr($question->id) . '">';
        $output .= '<h3>' . esc_html($question->question) . '</h3>';
        
        $question_options = array_filter($options, function($option) use ($question) {
            return $option->question_id == $question->id;
        });

        $output .= '<div class="options">';
        foreach ($question_options as $option) {
            $output .= '<label class="option">';
            $output .= '<input type="radio" name="question_' . esc_attr($question->id) . '" value="' . esc_attr($option->id) . '" required>';
            $output .= '<span class="option-text">' . esc_html($option->option_text) . '</span>';
            $output .= '</label>';
        }
        $output .= '</div>';
        $output .= '</div>';
    }
    $output .= '</div>';

    // Add navigation buttons if pagination is enabled
    if ($settings->is_paginated) {
        $output .= '<div class="quiz-navigation">';
        
        // Previous button
        if ($current_page > 1) {
            $prev_url = add_query_arg('page', $current_page - 1);
            $output .= '<a href="' . esc_url($prev_url) . '" class="button prev-button">Previous</a>';
        }
        
        // Next/Submit button
        if ($current_page < $total_pages) {
            $next_url = add_query_arg('page', $current_page + 1);
            $output .= '<a href="' . esc_url($next_url) . '" class="button next-button">Next</a>';
        } else {
            $output .= '<button type="submit" class="button submit-button">Submit Quiz</button>';
        }
        
        $output .= '</div>';
    } else {
        $output .= '<button type="submit" class="button submit-button">Submit Quiz</button>';
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
                saveAnswer(questionId, optionId);
            });
            
            // Load saved answers when page loads
            loadAnswers();
            
            // Handle form submission
            $(".quiz-form").on("submit", function(e) {
                // Add all saved answers to the form before submission
                addSavedAnswersToForm($(this));
                console.log("Form submitting with all saved answers");
                
                // Clear localStorage after adding answers to form
                localStorage.removeItem(storageKey);
                return true; // Allow form to submit normally
            });
            
            // Handle navigation buttons
            $(".next-button, .prev-button").click(function() {
                console.log("Navigation clicked, answers saved in localStorage");
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
    }
    .quiz-pagination-info {
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
    }
    </style>';

    return $output;
} 