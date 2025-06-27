<?php
if (!defined('ABSPATH')) exit;

class QB_Quiz_Settings_DB {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'qb_quiz_settings';
    }

    /**
     * Check if table exists
     */
    public function table_exists() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name ));
        
        return $table_exists === $this->table_name;
    }

    /**
     * Create or update the settings table
     */
    public function update_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();        // SQL for creating the table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            is_paginated tinyint(1) NOT NULL DEFAULT 0,
            questions_per_page int(11) NOT NULL DEFAULT 1,
            show_user_answers tinyint(1) NOT NULL DEFAULT 0,
            allow_pdf_export tinyint(1) NOT NULL DEFAULT 0,
            randomize_questions tinyint(1) NOT NULL DEFAULT 0,
            randomize_answers tinyint(1) NOT NULL DEFAULT 0,
            show_category_scores tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY quiz_id (quiz_id)
        ) $charset_collate;";// Use dbDelta for table creation/update
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        dbDelta($sql);
        // PCP: Direct DB select for schema check (schema management, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $show_answers_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s
            AND TABLE_NAME = %s
            AND COLUMN_NAME = 'show_user_answers'",
            $wpdb->dbname,
            $this->table_name
        ));

        // If column doesn't exist, add it
        if (empty($show_answers_exists)) {
            // PCP: Direct DB schema change (ALTER TABLE) for migration (plugin setup, safe in this context).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("ALTER TABLE {$this->table_name} 
                ADD COLUMN show_user_answers tinyint(1) NOT NULL DEFAULT 0 
                AFTER questions_per_page");
        }

        // Verify the allow_pdf_export column exists
        // PCP: Direct DB select for schema check (schema management, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pdf_export_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s
            AND TABLE_NAME = %s
            AND COLUMN_NAME = 'allow_pdf_export'",
            $wpdb->dbname,
            $this->table_name
        ));

        // If column doesn't exist, add it
        if (empty($pdf_export_exists)) {
            // PCP: Direct DB schema change (ALTER TABLE) for migration (plugin setup, safe in this context).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("ALTER TABLE {$this->table_name} 
                ADD COLUMN allow_pdf_export tinyint(1) NOT NULL DEFAULT 0 
                AFTER show_user_answers");
        }

        // Verify the randomize_questions column exists
        // PCP: Direct DB select for schema check (schema management, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $randomize_questions_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s
            AND TABLE_NAME = %s
            AND COLUMN_NAME = 'randomize_questions'",
            $wpdb->dbname,
            $this->table_name
        ));

        // If column doesn't exist, add it
        if (empty($randomize_questions_exists)) {
            // PCP: Direct DB schema change (ALTER TABLE) for migration (plugin setup, safe in this context).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("ALTER TABLE {$this->table_name} 
                ADD COLUMN randomize_questions tinyint(1) NOT NULL DEFAULT 0 
                AFTER allow_pdf_export");
        }

        // Verify the randomize_answers column exists
        // PCP: Direct DB select for schema check (schema management, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $randomize_answers_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s
            AND TABLE_NAME = %s
            AND COLUMN_NAME = 'randomize_answers'",
            $wpdb->dbname,
            $this->table_name
        ));        // If column doesn't exist, add it
        if (empty($randomize_answers_exists)) {
            // PCP: Direct DB schema change (ALTER TABLE) for migration (plugin setup, safe in this context).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("ALTER TABLE {$this->table_name} 
                ADD COLUMN randomize_answers tinyint(1) NOT NULL DEFAULT 0 
                AFTER randomize_questions");
        }

        // Verify the show_category_scores column exists
        // PCP: Direct DB select for schema check (schema management, no caching needed).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $category_scores_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s
            AND TABLE_NAME = %s
            AND COLUMN_NAME = 'show_category_scores'",
            $wpdb->dbname,
            $this->table_name
        ));

        // If column doesn't exist, add it
        if (empty($category_scores_exists)) {
            // PCP: Direct DB schema change (ALTER TABLE) for migration (plugin setup, safe in this context).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("ALTER TABLE {$this->table_name} 
                ADD COLUMN show_category_scores tinyint(1) NOT NULL DEFAULT 0 
                AFTER randomize_answers");
        }

        // Log any database errors
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
        if (!empty($wpdb->last_error)) {
        }
    }

    /**
     * Get settings for a specific quiz
     */
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is set internally and safe in this context.
    public function get_settings($quiz_id) {
         // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $this->wpdb->get_row($this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name is set internally and safe in this context.
            "SELECT * FROM {$this->table_name} WHERE quiz_id = %d", $quiz_id
        ));
    }    /**
     * Save settings for a quiz
     */
    public function save_settings($quiz_id, $settings) {        // Ensure all required fields are set with defaults
        $defaults = array(
            'is_paginated' => 0,
            'questions_per_page' => 1,
            'show_user_answers' => 0,
            'allow_pdf_export' => 0,
            'randomize_questions' => 0,
            'randomize_answers' => 0,
            'show_category_scores' => 0
        );
        
        $settings = array_merge($defaults, $settings);

        $existing = $this->get_settings($quiz_id);
        
        if ($existing) {
            $result = $this->wpdb->update(
                $this->table_name,
                $settings,
                ['quiz_id' => $quiz_id]
            );
        } else {
            $settings['quiz_id'] = $quiz_id;
            $result = $this->wpdb->insert(
                $this->table_name,
                $settings
            );
        }

        if ($result === false) {
        }

        return $result;
    }
}