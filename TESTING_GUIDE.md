# Testing Guide: Quiz Builder Plugin Features

## Overview
This guide covers testing for both the "Show detailed results" feature fix and the new "Allow PDF Export" functionality.

## Features Covered
1. **Show detailed results** - Fixed setting functionality 
2. **Allow PDF Export** - New feature for downloading quiz results as PDF

## What Was Fixed/Added

### Show Detailed Results Fix
- **Issue**: The "Show detailed results" setting in the admin interface existed but didn't function properly
- **Root Cause**: The `qb_display_quiz_results()` function always used the basic results template instead of checking the setting
- **Solution**: Modified the function to conditionally use the `QB_Quiz_Results_Display` class when detailed results are enabled

### PDF Export Feature (NEW)
- **Feature**: New "Allow PDF Export" setting that conditionally shows a PDF download button on results pages
- **Implementation**: 
  - Database schema updated to include `allow_pdf_export` column
  - Settings form includes new checkbox option
  - PDF generation using HTML templates with fallback support
  - Secure AJAX handler with nonce verification

## Files Modified

### Show Detailed Results Fix
1. **quiz-builder.php** (lines 286-304): Updated `qb_display_quiz_results()` function to:
   - Check if detailed results are enabled via `QB_Quiz_Settings_DB`
   - Use `QB_Quiz_Results_Display` class when enabled
   - Fall back to basic template when disabled

### PDF Export Feature
1. **includes/db/class-quiz-settings-db.php**: 
   - Added `allow_pdf_export` column to database schema
   - Updated default settings and save operations
2. **admin/quiz-settings-page.php**: 
   - Added PDF export checkbox to settings form
   - Updated form processing logic
3. **includes/results/class-quiz-results-display.php**: 
   - Added conditional PDF export button display
   - Made `get_attempt_answers()` method public
   - Added `get_pdf_export_button()` method
4. **quiz-builder.php**: 
   - Added AJAX handler `qb_export_pdf`
   - Implemented PDF generation functions
   - Added security and validation checks

## Testing Steps

### 1. Setup Test Environment
1. Ensure the plugin is activated in WordPress
2. Create a test quiz with multiple questions and options
3. Take the quiz and complete it to generate results

### 2. Test Basic Results (Default Behavior)
1. Go to **Quiz Builder > Quiz Settings** in WordPress admin
2. Select your test quiz
3. Ensure "Show User Answers" is **unchecked**
4. Ensure "Allow PDF Export" is **unchecked**
5. Save settings
6. View the quiz results page
7. **Expected**: Only basic score information should be displayed (score, percentage, retake button)
8. **Expected**: NO PDF download button should be visible

### 3. Test Detailed Results (Fixed Feature)
1. Go to **Quiz Builder > Quiz Settings** in WordPress admin
2. Select your test quiz
3. **Check** "Show User Answers"
4. Keep "Allow PDF Export" **unchecked**
5. Save settings
6. View the quiz results page
7. **Expected**: Detailed results table should be displayed showing:
   - Quiz title and basic score
   - Table with columns: Question, Your Answer, Correct Answer, Result
   - Each row shows a question with user's selection vs correct answer
   - Visual indicators (✓ for correct, ✗ for incorrect)
8. **Expected**: Still NO PDF download button (since PDF export is disabled)

### 4. Test PDF Export Feature (NEW)
1. Go to **Quiz Builder > Quiz Settings** in WordPress admin
2. Select your test quiz
3. **Check** both "Show User Answers" AND "Allow PDF Export"
4. Save settings
5. View the quiz results page
6. **Expected**: 
   - Detailed results table is displayed (from step 3)
   - PDF download button appears below the results: "📄 Download PDF Results"
7. Click the PDF download button
8. **Expected**: 
   - Browser should download an HTML file (fallback PDF)
   - File should contain styled quiz results with:
     - Quiz title header
     - Score summary section
     - Detailed answers table
     - Professional styling and layout

### 5. Test PDF Export Security
1. With PDF export enabled, right-click the PDF button and copy the link
2. Note the URL contains a security nonce
3. Try accessing the URL in a new browser tab/incognito
4. **Expected**: Should work initially but nonce will expire
5. Try modifying the attempt_id parameter in the URL
6. **Expected**: Should show "Quiz attempt not found" or security error

### 6. Test PDF Export Disabled
1. Go to **Quiz Builder > Quiz Settings** 
2. **Uncheck** "Allow PDF Export" (keep "Show User Answers" checked)
3. Save settings
4. View the quiz results page
5. **Expected**: 
   - Detailed results table still shows
   - PDF download button should disappear
6. If you still have the PDF export URL from step 5, try accessing it
7. **Expected**: Should show "PDF export is not enabled for this quiz"

### 7. Verify Setting Persistence
1. Toggle both "Show User Answers" and "Allow PDF Export" settings on/off
2. View results after each change
3. **Expected**: Results display should change accordingly and persist across page loads

## Key Components

### QB_Quiz_Results_Display Class
- Located in: `includes/results/class-quiz-results-display.php`
- Handles detailed results rendering
- Fetches user answers and compares with correct answers
- Generates HTML table with styling

### QB_Quiz_Settings_DB Class
- Located in: `includes/db/class-quiz-settings-db.php`
- Manages quiz settings storage and retrieval
- Handles the `show_user_answers` setting

### Basic Results Template
- Located in: `templates/quiz-results-html.php`
- Simple score display with retake button
- Used when detailed results are disabled

## Expected Behavior Differences

### Before Fix
- Setting existed in admin but had no effect
- Always showed basic results regardless of setting
- User answers were never displayed

### After Fix
- Setting controls result display type
- Basic results: Score + percentage + retake button
- Detailed results: Score + answers table + retake button
- Proper fallback if detailed results class fails

## Troubleshooting

### If Detailed Results Don't Show
1. Check that the quiz has the `show_user_answers` setting enabled in the database
2. Verify the attempt exists with valid answer data
3. Check browser console for JavaScript errors
4. Review WordPress error logs for PHP errors

### Database Verification
Run this SQL to check settings:
```sql
SELECT * FROM wp_qb_quiz_settings WHERE quiz_id = [YOUR_QUIZ_ID];
```
The `show_user_answers` column should be `1` for enabled.

### CSS Styling
The detailed results use CSS classes from `assets/css/quiz-results.css`:
- `.quiz-results` - Container
- `.quiz-answers-table` - Table styling
- `.correct-answer` - Green styling for correct answers
- `.incorrect-answer` - Red styling for wrong answers

## Success Criteria
✅ Settings interface works and saves properly  
✅ Basic results show when setting is disabled  
✅ Detailed results show when setting is enabled  
✅ Detailed results include user answers vs correct answers  
✅ Visual indicators show correct/incorrect answers  
✅ Setting changes are reflected immediately  
✅ No errors in browser console or WordPress logs  

## Notes
- The fix maintains backward compatibility
- Existing quiz results continue to work
- No database changes were required
- The detailed results class was already implemented but unused
