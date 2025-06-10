<?php
if (!defined('ABSPATH')) {
    exit;
}

class QB_Categories_DB {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'qb_categories';
    }

    /**
     * Create or update the categories table
     */
    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            color VARCHAR(7) DEFAULT '#3498db',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        dbDelta($sql);
        
        // Log any database errors
        if (!empty($this->wpdb->last_error)) {
            error_log('QB Categories DB Error: ' . $this->wpdb->last_error);
        }
    }

    /**
     * Get all categories
     */
    public function get_all_categories() {
        return $this->wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY name ASC");
    }

    /**
     * Get category by ID
     */
    public function get_category($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Insert new category
     */
    public function insert_category($data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'color' => sanitize_hex_color($data['color']),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update category
     */
    public function update_category($id, $data) {
        return $this->wpdb->update(
            $this->table_name,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'color' => sanitize_hex_color($data['color']),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Delete category
     */
    public function delete_category($id) {
        // First, check if category is being used by any questions
        $questions_using_category = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}qb_questions WHERE category_id = %d",
                $id
            )
        );

        if ($questions_using_category > 0) {
            return new WP_Error('category_in_use', 'Cannot delete category that is being used by questions.');
        }

        return $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Check if category name exists (for uniqueness validation)
     */
    public function category_name_exists($name, $exclude_id = null) {
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE name = %s";
        $params = array($name);

        if ($exclude_id) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }

        return $this->wpdb->get_var($this->wpdb->prepare($sql, $params)) > 0;
    }
}