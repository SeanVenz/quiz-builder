# Pagination + Randomization Fix

## Problem Description
When both pagination and question randomization were enabled, users experienced issues where:
- Some questions appeared multiple times across different pages
- Other questions never appeared at all
- The same questions would show different content when navigating back and forth

## Root Cause
The randomization was happening **every time** the quiz display function was called, which occurs on every page load during pagination. This meant:

1. User loads page 1 → questions get shuffled → shows first N questions  
2. User clicks "Next" → questions get shuffled **again** → shows different questions
3. Result: Inconsistent question sets and missing/duplicate questions

## Solution Implemented

### 1. **Session-Based Randomization Storage**
- Store the randomized order in PHP sessions on first load
- Reuse the stored order for all subsequent page navigation
- Ensures consistent question order throughout the quiz session

### 2. **Smart Session Management**
- **Session Start**: Clear any existing randomization when user starts fresh (no `quiz_page` parameter)
- **Session Persistence**: Maintain randomized order during pagination navigation  
- **Session Cleanup**: Clear randomization data when quiz is completed

## Technical Changes

### File: `templates/quiz-display.php`

#### **Before (Problematic)**:
```php
// Apply randomization if enabled
if (isset($settings->randomize_questions) && $settings->randomize_questions) {
    shuffle($questions); // ❌ Randomizes on EVERY page load
}
```

#### **After (Fixed)**:
```php
// Generate unique session keys for this quiz
$quiz_session_key = 'qb_randomized_questions_' . $quiz->id;
$options_session_key = 'qb_randomized_options_' . $quiz->id;

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Apply randomization if enabled, but only once per quiz session
if (isset($settings->randomize_questions) && $settings->randomize_questions) {
    if (isset($_SESSION[$quiz_session_key])) {
        // ✅ Use stored randomized order
        $randomized_ids = $_SESSION[$quiz_session_key];
        // Reorder questions according to stored order
        // ... reordering logic ...
    } else {
        // ✅ First time: randomize and store the order
        shuffle($questions);
        $_SESSION[$quiz_session_key] = array_map(function($q) { return $q->id; }, $questions);
    }
}
```

### File: `quiz-builder.php`

#### **Session Initialization (Fresh Start)**:
```php
// Clear randomization session if this is a fresh start (no pagination parameter)
if (!isset($_GET['quiz_page']) && session_status() != PHP_SESSION_DISABLED) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Clear any existing randomization for this quiz
    $quiz_session_key = 'qb_randomized_questions_' . $quiz_id;
    $options_session_key = 'qb_randomized_options_' . $quiz_id;
    unset($_SESSION[$quiz_session_key]);
    unset($_SESSION[$options_session_key]);
}
```

#### **Session Cleanup (Quiz Completion)**:
```php
// Clear randomization session data for this quiz since it's completed
if (session_status() != PHP_SESSION_DISABLED) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $quiz_session_key = 'qb_randomized_questions_' . $quiz_id;
    $options_session_key = 'qb_randomized_options_' . $quiz_id;
    unset($_SESSION[$quiz_session_key]);
    unset($_SESSION[$options_session_key]);
}
```

## How It Works Now

### **Scenario 1: Fresh Quiz Start**
1. User visits quiz page (no `quiz_page` parameter)
2. System clears any existing randomization session data
3. Questions get randomized and order stored in session
4. User sees first page with consistent questions

### **Scenario 2: Page Navigation**  
1. User clicks "Next" → URL includes `quiz_page=2`
2. System retrieves stored randomization order from session
3. Questions are reordered according to stored sequence
4. User sees consistent questions on page 2

### **Scenario 3: Quiz Completion**
1. User submits quiz successfully
2. System clears randomization session data
3. Next quiz attempt will start fresh with new randomization

## Benefits

### ✅ **Consistent Question Sets**
- Each question appears exactly once during pagination
- No duplicates or missing questions
- Reliable user experience

### ✅ **Proper Randomization** 
- Questions are still properly randomized on each fresh start
- Randomization works correctly with pagination
- Options can also be randomized consistently

### ✅ **Session Management**
- Automatic cleanup prevents memory leaks
- Fresh start clears old randomization
- Completion clears session data

### ✅ **Backward Compatibility**
- Works with existing quizzes
- No database changes required
- Graceful fallback if sessions are disabled

## Testing Scenarios

### ✅ **Test Case 1: Paginated + Randomized Questions**
1. Create quiz with 10 questions
2. Enable pagination (2 questions per page)  
3. Enable question randomization
4. Navigate through all pages
5. **Expected**: Each question appears exactly once, in randomized order

### ✅ **Test Case 2: Multiple Quiz Attempts**
1. Complete Test Case 1 fully
2. Start the quiz again from beginning
3. **Expected**: Different randomization order than first attempt

### ✅ **Test Case 3: Answer Randomization**
1. Enable both question and answer randomization
2. Navigate through paginated quiz
3. **Expected**: Both questions and answers maintain consistent order during navigation

## Files Modified
1. **`templates/quiz-display.php`** - Implemented session-based randomization storage
2. **`quiz-builder.php`** - Added session initialization and cleanup
3. **`PAGINATION_RANDOMIZATION_FIX.md`** - This documentation

## Performance Impact
- **Minimal**: Session storage is lightweight
- **Memory**: Small footprint (only stores question/option IDs)
- **Speed**: Faster than re-randomizing on every page load
