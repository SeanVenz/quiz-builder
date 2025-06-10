<?php
/**
 * Debug script to test database table creation
 * This file helps diagnose database creation issues
 */

// Set up WordPress environment (adjust path as needed)
require_once('../../../wp-config.php');

// Include our database functions
require_once('includes/db-functions.php');

echo "<h2>Quiz Builder Database Debug Script</h2>\n";

// Check if all functions exist
$functions = [
    'qb_create_quiz_table',
    'qb_create_categories_table', 
    'qb_create_questions_table',
    'qb_create_options_table',
    'qb_create_attempts_table',
    'qb_create_quiz_settings_table',
    'qb_create_database_tables'
];

echo "<h3>Function Checks:</h3>\n";
foreach ($functions as $func) {
    $exists = function_exists($func) ? '✅' : '❌';
    echo "$exists $func\n<br>";
}

echo "\n<h3>Current Database Tables:</h3>\n";
global $wpdb;

$tables = [
    'qb_quizzes',
    'qb_categories', 
    'qb_questions',
    'qb_options',
    'qb_attempts',
    'qb_quiz_settings'
];

foreach ($tables as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
    $status = $exists ? '✅' : '❌';
    echo "$status $full_table_name\n<br>";
    
    if ($exists) {
        // Show columns
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $full_table_name");
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li>{$column->Field} ({$column->Type})</li>";
        }
        echo "</ul>";
    }
}

echo "\n<h3>Testing Table Creation:</h3>\n";

try {
    echo "Creating database tables...<br>\n";
    qb_create_database_tables();
    echo "✅ Database tables creation completed<br>\n";
} catch (Exception $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "<br>\n";
}

echo "\n<h3>Tables After Creation:</h3>\n";
foreach ($tables as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
    $status = $exists ? '✅' : '❌';
    echo "$status $full_table_name\n<br>";
}

echo "\n<h3>WordPress Database Errors:</h3>\n";
if ($wpdb->last_error) {
    echo "❌ Last Error: " . $wpdb->last_error . "<br>\n";
} else {
    echo "✅ No WordPress database errors<br>\n";
}

// Test inserting a quiz
echo "\n<h3>Test Quiz Creation:</h3>\n";
try {
    $quiz_result = $wpdb->insert(
        $wpdb->prefix . 'qb_quizzes',
        array(
            'title' => 'Test Quiz Debug',
            'description' => 'Debug test quiz',
            'created_at' => current_time('mysql')
        )
    );
    
    if ($quiz_result) {
        $quiz_id = $wpdb->insert_id;
        echo "✅ Test quiz created with ID: $quiz_id<br>\n";
        
        // Test category creation
        $category_result = $wpdb->insert(
            $wpdb->prefix . 'qb_categories',
            array(
                'name' => 'Test Category',
                'description' => 'Debug test category',
                'color' => '#ff0000'
            )
        );
        
        if ($category_result) {
            $category_id = $wpdb->insert_id;
            echo "✅ Test category created with ID: $category_id<br>\n";
            
            // Test question creation
            $question_result = $wpdb->insert(
                $wpdb->prefix . 'qb_questions',
                array(
                    'quiz_id' => $quiz_id,
                    'category_id' => $category_id,
                    'question' => 'Test question?',
                    'order' => 1
                )
            );
            
            if ($question_result) {
                echo "✅ Test question created with ID: " . $wpdb->insert_id . "<br>\n";
            } else {
                echo "❌ Failed to create test question: " . $wpdb->last_error . "<br>\n";
            }
        } else {
            echo "❌ Failed to create test category: " . $wpdb->last_error . "<br>\n";
        }
    } else {
        echo "❌ Failed to create test quiz: " . $wpdb->last_error . "<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Exception during test: " . $e->getMessage() . "<br>\n";
}

echo "\n<h3>Debug Complete</h3>\n";
?>
