<?php
/*
Plugin Name: Quiz Builder
Description: A custom quiz builder plugin like QSM, but free and extensible.
Version: 1.0.0
Author: Sean Venz Quijano
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Main plugin class
class QuizBuilderPlugin {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_shortcode('quiz_builder_hello', array($this, 'hello_world_shortcode'));
    }

    // Add to admin menu
    public function register_admin_menu() {
        add_menu_page(
            'Quiz Builder',
            'Quiz Builder',
            'manage_options',
            'quiz-builder',
            array($this, 'admin_page_content'),
            'dashicons-welcome-learn-more',
            6
        );
    }

    // Admin page content
    public function admin_page_content() {
        echo '<div class="wrap"><h1>Quiz Builder</h1><p>Hello, world! Welcome to your custom quiz plugin.</p></div>';
    }

    // Shortcode output
    public function hello_world_shortcode() {
        return '<p>Hello from the Quiz Builder plugin!</p>';
    }
}

// Initialize plugin
new QuizBuilderPlugin();
