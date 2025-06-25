=== Quiz Builder ===
Contributors: pikocode
Tags: quiz, assessment, results, pdf, education
Requires at least: 6.2
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

# Quiz Builder

A comprehensive WordPress plugin for creating and managing quizzes with detailed results and PDF export functionality.

## Features

### Core Functionality
- Create and manage quizzes with multiple questions
- Track user attempts and scores
- Detailed results display with user answers
- Administrative interface for quiz management
- **Quiz Results Page** - Dedicated results display with `[quiz_results]` shortcode
- **Comprehensive Onboarding** - Step-by-step guide for creating both quizzes and results pages

### New: PDF Export Feature
- **Allow PDF Export** setting for individual quizzes  
- Professional PDF generation of quiz results
- Enhanced browser-based PDF creation with user guidance
- Secure download links with nonce verification

## Installation

1. Upload the plugin files to `/wp-content/plugins/quiz-builder/`
2. Activate the plugin through the WordPress admin
3. Database schema will be automatically updated

## Usage

### Quick Setup with Onboarding
1. Go to **Quiz Builder > Getting Started**
2. Follow the 3-step onboarding process:
   - **Step 1**: Create your quiz with title and description
   - **Step 2**: Add engaging questions
   - **Step 3**: Configure answer options and point values
3. After completion, you'll receive both quiz and results display instructions

### Displaying Your Quiz
Use the shortcode provided after onboarding:
```
[quiz_builder quiz_id="1"]
```

### Displaying Quiz Results
Create a separate "Quiz Results" page and use:

**For Gutenberg Editor:**
```
[quiz_results]
```

**For Custom PHP Themes:**
```php
<?php echo do_shortcode('[quiz_results]'); ?>
```

### For Administrators
1. Go to **Quiz Builder > Quiz Settings**
2. Select a quiz to configure
3. Enable "Show User Answers" for detailed results
4. Enable "Allow PDF Export" to show PDF download button
5. Save settings

### For Users
1. Complete a quiz
2. View results page (displays automatically or via dedicated results page)
3. If enabled, click "📄 Download PDF Results" button
4. Follow browser instructions to save as PDF

## File Structure

### Core Files
- `quiz-builder.php` - Main plugin file with PDF functionality
- `includes/class-pdf-manager.php` - Enhanced PDF management system
- `includes/db/class-quiz-settings-db.php` - Database operations
- `includes/results/class-quiz-results-display.php` - Results display
- `admin/quiz-settings-page.php` - Admin settings interface

### Templates
- `templates/quiz-display.php` - Quiz rendering
- `templates/quiz-results.php` - Results page
- `templates/quiz-results-html.php` - Basic results template
- `templates/onboarding.php` - Comprehensive setup wizard

### Assets
- `assets/css/quiz-results.css` - Results styling
- `assets/css/onboarding.css` - Onboarding interface styling
- `assets/js/onboarding.js` - Admin interface scripts and copy functionality

## Database Schema

The plugin creates/modifies these tables:
- `wp_qb_quiz_settings` - Quiz configuration (includes `allow_pdf_export` column)
- `wp_qb_quiz_attempts` - User quiz attempts
- `wp_qb_quiz_answers` - Individual question answers

## Shortcodes

### `[quiz_builder quiz_id="X"]`
Displays a specific quiz on any page or post.
- `quiz_id` (required) - The ID of the quiz to display

### `[quiz_results]`
Displays quiz results for the most recent quiz attempt.
- Automatically detects the current user's latest quiz completion
- Shows detailed results including answers and scores
- Can be placed on any page or post
- Works with both registered and guest users

## Security Features

- Nonce verification for PDF downloads
- Input sanitization and validation
- Permission checks for admin functions
- Secure AJAX handlers

## Browser Compatibility

- All modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile device support
- Print-to-PDF functionality built-in

## Support

For issues or questions, check the WordPress debug logs and browser console for error messages.

## Version History

- **Latest**: Enhanced onboarding with quiz results page setup
- Enhanced PDF export with browser-based generation
- Added "Allow PDF Export" quiz setting
- Added comprehensive onboarding wizard
- Added `[quiz_results]` shortcode for dedicated results pages
- Improved security and user experience
- Professional PDF formatting and styling

---

**Ready for Production Use** ✅
