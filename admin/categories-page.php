<?php
if (!defined('ABSPATH')) exit;

// Include the Categories database class
require_once plugin_dir_path(__FILE__) . '../includes/db/class-categories-db.php';

/**
 * Clear all category question count cache
 */
function qb_clear_category_question_count_cache() {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'qb_categories';
    
    // PCP: Direct DB query is used here to fetch all category IDs for cache clearing only. Caching is not needed.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is set internally and safe in this context.
    $category_ids = $wpdb->get_col("SELECT id FROM {$categories_table}");
    
    if ($category_ids) {
        foreach ($category_ids as $category_id) {
            $cache_key = 'qb_category_question_count_' . $category_id;
            wp_cache_delete($cache_key);
        }
    }
}

// Add action handlers for category management
add_action('admin_post_qb_add_category', 'qb_handle_add_category');
add_action('admin_post_qb_edit_category', 'qb_handle_edit_category');
add_action('admin_post_qb_delete_category', 'qb_handle_delete_category');

function qb_handle_add_category() {    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('qb_add_category');
    
    $categories_db = new QB_Categories_DB();
    
    $name = isset($_POST['category_name']) ? sanitize_text_field(wp_unslash($_POST['category_name'])) : '';
    $description = isset($_POST['category_description']) ? sanitize_textarea_field(wp_unslash($_POST['category_description'])) : '';
    $color = isset($_POST['category_color']) ? sanitize_hex_color(wp_unslash($_POST['category_color'])) : '';
    
    if (empty($name)) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'qb-categories',
            'message' => 'name_required'
        ), admin_url('admin.php')));
        exit;
    }
    
    if ($categories_db->category_name_exists($name)) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'qb-categories',
            'message' => 'name_exists'
        ), admin_url('admin.php')));
        exit;
    }
      $result = $categories_db->insert_category(array(
        'name' => $name,
        'description' => $description,
        'color' => $color ?: '#3498db'
    ));
    
    // Clear cache if category was added successfully
    if ($result) {
        qb_clear_category_question_count_cache();
    }
    
    $message = $result ? 'category_added' : 'category_error';
    
    wp_safe_redirect(add_query_arg(array(
        'page' => 'qb-categories',
        'message' => $message
    ), admin_url('admin.php')));
    exit;
}

function qb_handle_edit_category() {    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('qb_edit_category');
    
    $categories_db = new QB_Categories_DB();
    
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $name = isset($_POST['category_name']) ? sanitize_text_field(wp_unslash($_POST['category_name'])) : '';
    $description = isset($_POST['category_description']) ? sanitize_textarea_field(wp_unslash($_POST['category_description'])) : '';
    $color = isset($_POST['category_color']) ? sanitize_hex_color(wp_unslash($_POST['category_color'])) : '';
    
    if (empty($name)) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'qb-categories',
            'message' => 'name_required'
        ), admin_url('admin.php')));
        exit;
    }
    
    if ($categories_db->category_name_exists($name, $category_id)) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'qb-categories',
            'message' => 'name_exists'
        ), admin_url('admin.php')));
        exit;
    }
      $result = $categories_db->update_category($category_id, array(
        'name' => $name,
        'description' => $description,
        'color' => $color ?: '#3498db'
    ));
    
    // Clear cache if category was updated successfully
    if ($result !== false) {
        qb_clear_category_question_count_cache();
    }
    
    $message = $result !== false ? 'category_updated' : 'category_error';
    
    wp_safe_redirect(add_query_arg(array(
        'page' => 'qb-categories',
        'message' => $message
    ), admin_url('admin.php')));
    exit;
}

function qb_handle_delete_category() {    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('qb_delete_category');
    
    $categories_db = new QB_Categories_DB();
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
      $result = $categories_db->delete_category($category_id);
    
    // Clear cache if category was deleted successfully
    if (!is_wp_error($result) && $result !== false) {
        qb_clear_category_question_count_cache();
    }
    
    if (is_wp_error($result)) {
        $message = 'category_in_use';
    } else {
        $message = $result !== false ? 'category_deleted' : 'category_error';
    }
    
    wp_safe_redirect(add_query_arg(array(
        'page' => 'qb-categories',
        'message' => $message
    ), admin_url('admin.php')));
    exit;
}

/**
 * Categories management page
 */
function qb_categories_page() {
    $categories_db = new QB_Categories_DB();
      // Show messages
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['message'])) {
        // Nonce verification recommended for form processing.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Recommended -- $_GET['message'] is sanitized but not unslashed.
        $message = sanitize_text_field($_GET['message']);
        switch ($message) {
            case 'category_added':
                echo '<div class="notice notice-success is-dismissible"><p>Category added successfully!</p></div>';
                break;
            case 'category_updated':
                echo '<div class="notice notice-success is-dismissible"><p>Category updated successfully!</p></div>';
                break;
            case 'category_deleted':
                echo '<div class="notice notice-success is-dismissible"><p>Category deleted successfully!</p></div>';
                break;
            case 'category_in_use':
                echo '<div class="notice notice-error is-dismissible"><p>Cannot delete category that is being used by questions.</p></div>';
                break;
            case 'name_required':
                echo '<div class="notice notice-error is-dismissible"><p>Category name is required.</p></div>';
                break;
            case 'name_exists':
                echo '<div class="notice notice-error is-dismissible"><p>A category with this name already exists.</p></div>';
                break;
            case 'category_error':
                echo '<div class="notice notice-error is-dismissible"><p>Error processing category. Please try again.</p></div>';
                break;
        }
    }
    
    // Get all categories
    $categories = $categories_db->get_all_categories();
    ?>
    
    <div class="wrap">
        <h1>Quiz Categories</h1>
        <p>Organize your quiz questions by category. Categories help you create more organized quizzes and can be used for sub-score calculations.</p>
        
        <div class="qb-categories-container">
            <div class="qb-add-category-section">
                <h2>Add New Category</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="qb-category-form">
                    <?php wp_nonce_field('qb_add_category'); ?>
                    <input type="hidden" name="action" value="qb_add_category">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="category_name">Category Name <span class="required">*</span></label></th>
                            <td>
                                <input name="category_name" type="text" id="category_name" class="regular-text" required>
                                <p class="description">Enter a unique name for this category</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="category_description">Description</label></th>
                            <td>
                                <textarea name="category_description" id="category_description" rows="3" class="large-text" placeholder="Optional description of what this category covers..."></textarea>
                                <p class="description">Brief description of this category (optional)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="category_color">Color</label></th>
                            <td>
                                <input name="category_color" type="color" id="category_color" value="#3498db" class="qb-color-picker">
                                <p class="description">Choose a color to represent this category</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Add Category', 'primary', 'submit', false); ?>
                </form>
            </div>
            
            <hr>
            
            <div class="qb-categories-list-section">
                <h2>Existing Categories</h2>
                
                <?php if ($categories): ?>
                    <table class="wp-list-table widefat fixed striped qb-categories-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Color</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Questions Count</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>                            <?php foreach ($categories as $category): ?>
                                <?php
                                // Get question count for this category with caching
                                $cache_key = 'qb_category_question_count_' . $category->id;
                                $question_count = wp_cache_get($cache_key);
                                
                                if (false === $question_count) {
                                    global $wpdb;
                                    // PCP: Direct DB query is used here to count questions for this category. Result is cached for performance.
                                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                                    $question_count = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$wpdb->prefix}qb_questions WHERE category_id = %d",
                                        $category->id
                                    ));
                                    
                                    // Cache the result for 1 hour (3600 seconds)
                                    wp_cache_set($cache_key, $question_count, '', 3600);
                                }
                                ?>
                                <tr data-category-id="<?php echo esc_attr($category->id); ?>">
                                    <td>
                                        <div class="qb-color-indicator" style="background-color: <?php echo esc_attr($category->color); ?>; width: 20px; height: 20px; border-radius: 50%; border: 1px solid #ddd;"></div>
                                    </td>
                                    <td>
                                        <strong class="category-name"><?php echo esc_html($category->name); ?></strong>
                                        <div class="edit-category-form" style="display: none;">
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                <?php wp_nonce_field('qb_edit_category'); ?>
                                                <input type="hidden" name="action" value="qb_edit_category">
                                                <input type="hidden" name="category_id" value="<?php echo esc_attr($category->id); ?>">
                                                
                                                <input type="text" name="category_name" value="<?php echo esc_attr($category->name); ?>" class="regular-text" required>
                                                <textarea name="category_description" class="large-text" rows="2"><?php echo esc_textarea($category->description); ?></textarea>
                                                <input type="color" name="category_color" value="<?php echo esc_attr($category->color); ?>">
                                                
                                                <button type="submit" class="button button-primary">Save</button>
                                                <button type="button" class="button cancel-edit">Cancel</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="category-description"><?php echo esc_html($category->description ?: 'No description'); ?></span>
                                    </td>
                                    <td>
                                        <span class="question-count"><?php echo intval($question_count); ?></span>
                                    </td>
                                    <td><?php echo esc_html(gmdate('M j, Y', strtotime($category->created_at))); ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <button class="button edit-category" data-id="<?php echo esc_attr($category->id); ?>">Edit</button>
                                            
                                            <?php if ($question_count == 0): ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    <?php wp_nonce_field('qb_delete_category'); ?>
                                                    <input type="hidden" name="action" value="qb_delete_category">
                                                    <input type="hidden" name="category_id" value="<?php echo esc_attr($category->id); ?>">
                                                    <button type="submit" class="button delete-category">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="description" title="Cannot delete category with questions">Cannot Delete</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="qb-no-categories">
                        <p>No categories found. Create your first category above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .qb-categories-container {
            max-width: 1200px;
        }
        
        .qb-category-form {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        
        .qb-categories-table .qb-color-indicator {
            display: inline-block;
        }
        
        .edit-category-form {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .edit-category-form input[type="text"],
        .edit-category-form textarea {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .edit-category-form input[type="color"] {
            margin-bottom: 10px;
        }
        
        .row-actions {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .delete-category {
            color: #dc3232;
        }
        
        .required {
            color: #dc3232;
        }
        
        .qb-no-categories {
            text-align: center;
            padding: 40px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Edit category functionality
        $('.edit-category').on('click', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            $row.find('.category-name, .category-description').hide();
            $row.find('.edit-category-form').show();
        });

        // Cancel edit
        $('.cancel-edit').on('click', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            $row.find('.category-name, .category-description').show();
            $row.find('.edit-category-form').hide();
        });
    });
    </script>
    
    <?php
}