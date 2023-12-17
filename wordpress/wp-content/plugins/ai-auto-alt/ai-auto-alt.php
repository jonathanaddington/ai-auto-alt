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

// Register settings and fields
function ai_auto_alt_register_settings() {
    register_setting(PLUGIN_NAMESPACE . '_options_group', PLUGIN_NAMESPACE . '_settings');

    add_settings_section(
        PLUGIN_NAMESPACE . '_settings_section',
        'AI Auto Alt Settings',
        'ai_auto_alt_settings_section_cb',
        PLUGIN_NAMESPACE
    );

    add_settings_field(
        'ai_auto_alt_api_key',
        'OpenAI API Key',
        'ai_auto_alt_api_key_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_settings_section',
        array('label_for' => 'ai_auto_alt_api_key')
    );

    add_settings_field(
        'ai_auto_alt_model',
        'OpenAI Model',
        'ai_auto_alt_model_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_settings_section',
        array('label_for' => 'ai_auto_alt_model')
    );

    add_settings_field(
        'ai_auto_alt_media_types',
        'Media Attachment Types',
        'ai_auto_alt_media_types_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_settings_section',
        array('label_for' => 'ai_auto_alt_media_types')
    );

    add_settings_field(
        'ai_auto_alt_openai_prompt',
        'OpenAI Prompt',
        'ai_auto_alt_openai_prompt_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_settings_section',
        array('label_for' => 'ai_auto_alt_openai_prompt')
    );
}

add_action('admin_init', 'ai_auto_alt_register_settings');

// Activation hook
function ai_auto_alt_activate() {
    // Set default settings
    $default_settings = array(
        'OPENAI_API_KEY' => '',
        'OPENAI_MODEL' => 'gpt-4-1106-preview',
        'MEDIA_ATTACHMENT_TYPES' => array('jpg', 'jpeg', 'png', 'gif', 'webp'),
        'OPENAI_PROMPT' => "You are an expert in web development for the visually impaired. I am going to give you an image I want you to generate alternate text for. This is expressly to help visually impaired persons navigate the website, so you should focus on text that explains what the image does and the context of it, rather than long verbose descriptions.\n\nPlease review your work before returning text.\n\nThe image is: {{URL}}"
    );
    
    // Add default settings to the database if they don't already exist
    if (!get_option(PLUGIN_NAMESPACE . '_settings')) {
        add_option(PLUGIN_NAMESPACE . '_settings', $default_settings);
    }
}

register_activation_hook(__FILE__, 'ai_auto_alt_activate');

// Deactivation hook
function ai_auto_alt_deactivate() {
    // Actions to perform on plugin deactivation
    delete_option(PLUGIN_NAMESPACE . '_settings');
}

register_deactivation_hook(__FILE__, 'ai_auto_alt_deactivate');

// Callback for the settings section
function ai_auto_alt_settings_section_cb() {
    echo '<p>Enter your OpenAI settings below:</p>';
}

// Callbacks for each settings field go here
// Callback for the API key field
function ai_auto_alt_api_key_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_api_key" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_API_KEY]" type="text" value="' . esc_attr($options['OPENAI_API_KEY']) . '" class="regular-text" />';
}

// Callback for the model field
function ai_auto_alt_model_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_model" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_MODEL]" type="text" value="' . esc_attr($options['OPENAI_MODEL']) . '" class="regular-text" />';
}

// Callback for the media attachment types field
function ai_auto_alt_media_types_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    $types = implode(', ', (array) $options['MEDIA_ATTACHMENT_TYPES']); // Cast to array for safety
    echo '<input id="ai_auto_alt_media_types" name="' . PLUGIN_NAMESPACE . '_settings[MEDIA_ATTACHMENT_TYPES]" type="text" value="' . esc_attr($types) . '" class="regular-text" />';
}

// Callback for the OpenAI prompt field
function ai_auto_alt_openai_prompt_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    $prompt = $options['OPENAI_PROMPT'];
    echo '<textarea id="ai_auto_alt_openai_prompt" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_PROMPT]" rows="5" cols="50" class="large-text code">' . esc_textarea($prompt) . '</textarea>';
}

// Remember to add these callback functions to the respective add_settings_field calls

// Function to create settings page
function ai_auto_alt_settings_page() {
    add_options_page(
        'AI Auto Alt Settings',
        'AI Auto Alt',
        'manage_options',
        PLUGIN_NAMESPACE . '_settings',
        'ai_auto_alt_display_settings'
    );
}

add_action( 'admin_menu', 'ai_auto_alt_settings_page' );

// Display function for the settings page content
function ai_auto_alt_display_settings() {
    ?>
    <div class="wrap">
        <h2>AI Auto Alt Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields(PLUGIN_NAMESPACE . '_options_group'); ?>
            <?php do_settings_sections(PLUGIN_NAMESPACE); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
