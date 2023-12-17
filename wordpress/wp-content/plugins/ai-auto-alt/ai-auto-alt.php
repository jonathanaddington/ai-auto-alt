<?php
/**
 * Plugin Name: AI Auto Alt
 * Description: Uses OpenAI to automatically add alt text to images
 * Version: 1.0-alpha
 * Author: Jonathan Addington
 * Author URI: https://jonathanaddington.com
 */

define( 'PLUGIN_NAMESPACE', 'ai_auto_alt' );

// Hook for when a media file is uploaded
function ai_auto_alt_media_upload_hook( $attachment_id ) {
    // Your code to execute when a media file is uploaded
}

add_action( 'add_attachment', PLUGIN_NAMESPACE . '_media_upload_hook' );

// Function to create an empty settings page
function ai_auto_alt_settings_page() {
    add_options_page(
        'AI Auto Alt Settings',               // The text to be displayed in the title tags of the page when the menu is selected
        'AI Auto Alt',                        // The text to be used for the menu
        'manage_options',                     // The capability required for this menu to be displayed to the user
        PLUGIN_NAMESPACE . '_settings',       // The slug name to refer to this menu by (should be unique for this menu)
        PLUGIN_NAMESPACE . '_display_settings' // The function to be called to output the content for this page.
    );
}

// Hook to add settings page to the admin menu
add_action( 'admin_menu', PLUGIN_NAMESPACE . '_settings_page' );

// Display function for the settings page content
function ai_auto_alt_display_settings() {
    ?>
    <div class="wrap">
        <h2>AI Auto Alt Settings</h2>
        <form method="post" action="options.php">
            <!-- Your settings form inputs go here -->
        </form>
    </div>
    <?php
}

// Activation hook
function ai_auto_alt_activate() {
    // Set default settings
    $default_settings = array(
        'OPENAI_API_KEY' => '',
        'OPENAI_MODEL' => 'gpt-4-1106-preview', // Set the default model to gpt-4-1106-preview
        'MEDIA_ATTACHMENT_TYPES' => array('jpg', 'jpeg', 'png', 'gif', 'webp'), // Default media types as extensions
    );
    
    // Add default settings to the database if they don't already exist
    if (!get_option('ai_auto_alt_settings')) {
        add_option('ai_auto_alt_settings', $default_settings);
    }
}

register_activation_hook(__FILE__, 'ai_auto_alt_activate');

register_activation_hook( __FILE__, PLUGIN_NAMESPACE . '_activate' );

// Deactivation hook
function ai_auto_alt_deactivate() {
    // Actions to perform once on plugin deactivation
}

register_deactivation_hook( __FILE__, PLUGIN_NAMESPACE . '_deactivate' );
