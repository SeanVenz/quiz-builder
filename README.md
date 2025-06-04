# Quiz Builder WordPress Plugin

A comprehensive WordPress plugin for creating and managing quizzes with detailed results and PDF export functionality.

## Features

### Core Functionality
- Create and manage quizzes with multiple questions
- Track user attempts and scores
- Detailed results display with user answers
- Administrative interface for quiz management

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

### For Administrators
1. Go to **Quiz Builder > Quiz Settings**
2. Select a quiz to configure
3. Enable "Show User Answers" for detailed results
4. Enable "Allow PDF Export" to show PDF download button
5. Save settings

### For Users
1. Complete a quiz
2. View results page
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

### Assets
- `assets/css/quiz-results.css` - Results styling
- `assets/js/onboarding.js` - Admin interface scripts

## Database Schema

The plugin creates/modifies these tables:
- `wp_qb_quiz_settings` - Quiz configuration (includes `allow_pdf_export` column)
- `wp_qb_quiz_attempts` - User quiz attempts
- `wp_qb_quiz_answers` - Individual question answers

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

- **Latest**: Enhanced PDF export with browser-based generation
- Added "Allow PDF Export" quiz setting
- Improved security and user experience
- Professional PDF formatting and styling

---

**Ready for Production Use** ✅
