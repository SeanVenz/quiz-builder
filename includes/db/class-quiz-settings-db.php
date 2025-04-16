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
     * Create or update the settings table
     */
    public function update_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // SQL for creating the table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            is_paginated tinyint(1) NOT NULL DEFAULT 0,
            questions_per_page int(11) NOT NULL DEFAULT 1,
            show_user_answers tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY quiz_id (quiz_id)
        ) $charset_collate;";

        // Use dbDelta for table creation/update
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify the column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s
            AND TABLE_NAME = %s
            AND COLUMN_NAME = 'show_user_answers'",
            DB_NAME,
            $this->table_name
        ));

        // If column doesn't exist, add it
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$this->table_name} 
                ADD COLUMN show_user_answers tinyint(1) NOT NULL DEFAULT 0 
                AFTER questions_per_page");
        }

        // Log any database errors
        if (!empty($wpdb->last_error)) {
            error_log('Quiz Builder DB Error: ' . $wpdb->last_error);
        }
    }

    /**
     * Get settings for a specific quiz
     */
    public function get_settings($quiz_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE quiz_id = %d",
            $quiz_id
        ));
    }

    /**
     * Save settings for a quiz
     */
    public function save_settings($quiz_id, $settings) {
        // Ensure all required fields are set with defaults
        $settings = wp_parse_args($settings, array(
            'is_paginated' => 0,
            'questions_per_page' => 1,
            'show_user_answers' => 0
        ));

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
            error_log('Quiz Builder Settings Save Error: ' . $this->wpdb->last_error);
        }

        return $result;
    }
} 