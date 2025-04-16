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
