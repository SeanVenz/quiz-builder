<?php
if (!defined('ABSPATH')) exit;

function qb_create_quiz_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_quizzes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function qb_create_questions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_questions';
    $charset_collate = $wpdb->get_charset_collate();

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    // Create or update table
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        quiz_id BIGINT(20) UNSIGNED NOT NULL,
        question TEXT NOT NULL,
        `order` INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (quiz_id) REFERENCES {$wpdb->prefix}qb_quizzes(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // If table existed before, update existing questions with order
    if ($table_exists) {
        // Check if order column exists
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'order'");
        if (!$column_exists) {
            // Add order column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `order` INT DEFAULT 0");
            
            // Update existing questions with order based on their ID
            $wpdb->query("UPDATE $table_name SET `order` = id WHERE `order` = 0");
        }
    }
}

function qb_create_options_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_options';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id BIGINT(20) UNSIGNED NOT NULL,
        option_text VARCHAR(255) NOT NULL,
        points INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}qb_questions(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function qb_create_attempts_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_attempts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        random_id VARCHAR(32) NOT NULL,
        quiz_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        score INT NOT NULL,
        total_points INT NOT NULL,
        answers TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY random_id (random_id),
        FOREIGN KEY (quiz_id) REFERENCES {$wpdb->prefix}qb_quizzes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function qb_create_quiz_settings_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_quiz_settings';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        quiz_id BIGINT(20) UNSIGNED NOT NULL,
        questions_per_page INT DEFAULT 1,
        is_paginated TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY quiz_id (quiz_id),
        FOREIGN KEY (quiz_id) REFERENCES {$wpdb->prefix}qb_quizzes(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function qb_create_categories_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_categories';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        color VARCHAR(7) DEFAULT '#3498db',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY name (name)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function qb_create_database_tables() {
    // Create all necessary tables
    if (function_exists('qb_create_quiz_table')) {
        qb_create_quiz_table();
    }

    if (function_exists('qb_create_questions_table')) {
        qb_create_questions_table();
    }

    if (function_exists('qb_create_options_table')) {
        qb_create_options_table();
    }

    if (function_exists('qb_create_attempts_table')) {
        qb_create_attempts_table();
    }

    if (function_exists('qb_create_quiz_settings_table')) {
        qb_create_quiz_settings_table();
    }

    if (function_exists('qb_create_categories_table')) {
        qb_create_categories_table();
    }

    // Create quiz results page if it doesn't exist
    $results_page = get_page_by_path('quiz-results');
    if (!$results_page) {
        $page_data = array(
            'post_title'    => 'Quiz Results',
            'post_name'     => 'quiz-results',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '[quiz_results]'
        );
        wp_insert_post($page_data);
    }
}
