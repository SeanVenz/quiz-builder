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
    
    // Create or update table (without foreign keys for dbDelta compatibility)
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        quiz_id BIGINT(20) UNSIGNED NOT NULL,
        category_id BIGINT(20) UNSIGNED DEFAULT NULL,
        question TEXT NOT NULL,
        required TINYINT(1) NOT NULL DEFAULT 0,
        `order` INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY quiz_id (quiz_id),        KEY category_id (category_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Add foreign key constraints after table creation (more reliable than dbDelta with FKs)
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $categories_table = $wpdb->prefix . 'qb_categories';
    
    // Check if foreign key constraints exist and add them if they don't
    $quiz_fk_exists = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '$table_name' 
        AND COLUMN_NAME = 'quiz_id' 
        AND REFERENCED_TABLE_NAME = '$quizzes_table'
    ");
    
    if (!$quiz_fk_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (quiz_id) REFERENCES $quizzes_table(id) ON DELETE CASCADE");
    }
    
    // Only add category foreign key if categories table exists
    $categories_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'") === $categories_table;
    if ($categories_table_exists) {
        $category_fk_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '$table_name' 
            AND COLUMN_NAME = 'category_id' 
            AND REFERENCED_TABLE_NAME = '$categories_table'
        ");
        
        if (!$category_fk_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (category_id) REFERENCES $categories_table(id) ON DELETE SET NULL");
        }
    }// If table existed before, update existing questions with order
    if ($table_exists) {
        // Check if order column exists
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'order'");
        if (!$column_exists) {
            // Add order column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `order` INT DEFAULT 0");
            
            // Update existing questions with order based on their ID
            $wpdb->query("UPDATE $table_name SET `order` = id WHERE `order` = 0");
        }
        
        // Check if category_id column exists
        $category_column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'category_id'");
        if (!$category_column_exists) {
            // Add category_id column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN category_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER quiz_id");
            
            // Add foreign key constraint if categories table exists
            $categories_table = $wpdb->prefix . 'qb_categories';
            $categories_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'") === $categories_table;
            if ($categories_table_exists) {
                // Check if foreign key constraint already exists before adding it
                $foreign_key_exists = $wpdb->get_var("
                    SELECT COUNT(*) 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = '$table_name' 
                    AND COLUMN_NAME = 'category_id' 
                    AND REFERENCED_TABLE_NAME = '$categories_table'
                ");
                
                if (!$foreign_key_exists) {
                    $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (category_id) REFERENCES $categories_table(id) ON DELETE SET NULL");
                }
            }
        }
        
        // Check if required column exists
        $required_column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'required'");
        if (!$required_column_exists) {
            // Add required column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN required TINYINT(1) NOT NULL DEFAULT 0 AFTER question");
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
        KEY question_id (question_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    // Add foreign key constraint after table creation
    $questions_table = $wpdb->prefix . 'qb_questions';
    $questions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$questions_table'") === $questions_table;
    
    if ($questions_table_exists) {
        $fk_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '$table_name' 
            AND COLUMN_NAME = 'question_id' 
            AND REFERENCED_TABLE_NAME = '$questions_table'
        ");
        
        if (!$fk_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (question_id) REFERENCES $questions_table(id) ON DELETE CASCADE");
        }
    }
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
        KEY quiz_id (quiz_id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    // Add foreign key constraints after table creation
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $users_table = $wpdb->users;
    
    // Add quiz foreign key
    $quiz_fk_exists = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '$table_name' 
        AND COLUMN_NAME = 'quiz_id' 
        AND REFERENCED_TABLE_NAME = '$quizzes_table'
    ");
    
    if (!$quiz_fk_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (quiz_id) REFERENCES $quizzes_table(id) ON DELETE CASCADE");
    }
    
    // Add user foreign key
    $user_fk_exists = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '$table_name' 
        AND COLUMN_NAME = 'user_id' 
        AND REFERENCED_TABLE_NAME = '$users_table'
    ");
    
    if (!$user_fk_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (user_id) REFERENCES $users_table(ID) ON DELETE SET NULL");
    }
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
        KEY quiz_id_key (quiz_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Add foreign key constraint after table creation
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $quizzes_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$quizzes_table'") === $quizzes_table;
    
    if ($quizzes_table_exists) {
        $fk_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '$table_name' 
            AND COLUMN_NAME = 'quiz_id' 
            AND REFERENCED_TABLE_NAME = '$quizzes_table'
        ");
        
        if (!$fk_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (quiz_id) REFERENCES $quizzes_table(id) ON DELETE CASCADE");        }
    }
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
    // Create all necessary tables in the correct order (parent tables first)
    if (function_exists('qb_create_quiz_table')) {
        qb_create_quiz_table();
    }

    // Create categories table before questions table (due to foreign key)
    if (function_exists('qb_create_categories_table')) {
        qb_create_categories_table();
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
