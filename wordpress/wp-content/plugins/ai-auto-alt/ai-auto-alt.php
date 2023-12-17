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

    error_log('ai_auto_alt_media_upload_hook triggered for attachment ID ' . $attachment_id);

    // Retrieve the plugin settings
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    $prompt = $options['OPENAI_PROMPT'];

    // Allow for local debugging, where a public URL is not available, so we use a local file
    // with a list of URLs to use for testing.
    if (isset($options['AI_AUTO_ALT_LOCAL_DEBUG']) && $options['AI_AUTO_ALT_LOCAL_DEBUG']) {
        $images_md_path = isset($options['AI_AUTO_ALT_IMAGES_MD_PATH']) ? $options['AI_AUTO_ALT_IMAGES_MD_PATH'] : '/var/www/html/wp-content/uploads/2020/01/images.md';
        if ( file_exists($images_md_path) ) {
            $lines = file($images_md_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                $random_line = $lines[array_rand($lines)];
                $attachment_url = trim($random_line); // Assume each line contains only a single URL
            } else {
                error_log('The images.md file is empty or could not be read.');
            }
        } else {
            error_log('The images.md file does not exist at the specified location.');
        }
    } else {
        // Get the full URL to the image
        $attachment_url = wp_get_attachment_url($attachment_id);
    }

    // Get the full path to the image file on the server
    $attachment_path = get_attached_file($attachment_id);

    // Get the filename of the image from its URL
    $filename = basename($attachment_url);
    $prompt .= "\n\nImage filename: " . $filename;


    // Attempt to get the EXIF data if the file is a JPG or TIFF
    $exif_data = '';
    if (in_array(strtolower(pathinfo($attachment_path, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'tiff', 'tif'))) {
        $exif = wp_read_image_metadata($attachment_path);
        error_log('Image filename: ' . $filename . ' | EXIF Data: ' . $exif_data);

        if ($exif) {
            $exif_data = json_encode($exif); // Convert EXIF data to JSON string
            // Optionally you can create a formatted string of EXIF details that you're interested in.
            // For example: $exif_data = "Camera: {$exif['camera']}, ISO: {$exif['iso']}...";
            // Remember to sanitize any data appropriately.

            $prompt .= "I have some EXIF data to share, if it helps. \n\nEXIF Data: " . $exif_data;
        }
    }

    // Example prompt from OpenAI docs
    /*
    curl https://api.openai.com/v1/chat/completions \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $OPENAI_API_KEY" \
    -d '{
        "model": "gpt-4-vision-preview",
        "messages": [
        {
            "role": "user",
            "content": [
            {
                "type": "text",
                "text": "What’s in this image?"
            },
            {
                "type": "image_url",
                "image_url": {
                "url": "https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg"
                }
            }
            ]
        }
        ],
        "max_tokens": 300
    }'
    */

    // Create the API request
    // Create the prompt
    $request_data = [
        'model' => $options['OPENAI_MODEL'],
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt  // Using the filled prompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => $attachment_url  // Directly passing the URL
                    ]
                ]
            ],
        ],
        'temperature' => $options['OPENAI_TEMPERATURE'],
        'top_p' => $options['OPENAI_TOP_P'],
        'max_tokens' => $options['OPEN_AI_MAX_TOKENS']
    ];

    /* Sample return data based on the OpenAI docs

    {
        "id": "chatcmpl-123",
        "object": "chat.completion",
        "created": 1677652288,
        "model": "gpt-4-vision-preview",
        "system_fingerprint": "fp_44709d6fcb",
        "choices": [{
            "index": 0,
            "message": {
            "role": "assistant",
            "content": "\n\nHello there, how may I assist you today?",
            },
            "logprobs": null,
            "finish_reason": "stop"
        }],
        "usage": {
            "prompt_tokens": 9,
            "completion_tokens": 12,
            "total_tokens": 21
        }
    }

    */

    // Encode the request data
    $request_data_json = json_encode($request_data);

    error_log('Sending request to OpenAI: ' . $request_data_json);

    // Create the PHP request
    $request = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $options['OPENAI_API_KEY']
            ),
            'body' => $request_data_json,
            'timeout' => 30 // Setting the timeout to 30 seconds
        )
    );

        // Handle the response
    if (is_wp_error($request)) {
        // Handle error
        $error_message = $request->get_error_message();
        // Log or notify the error
        error_log('OpenAI request error: ' . $error_message);

    } else {
        $response_body = wp_remote_retrieve_body($request);
        $response_data = json_decode($response_body, true);
        error_log('OpenAI response: ' . $response_body);

        // Check if the response contains the expected data
        if (isset($response_data['choices'][0]['message']['content'])) {
            // Do something with the response
            $alt_text = $response_data['choices'][0]['message']['content'];
            // Update the attachment post with the alt text
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        } else {
            // Handle unexpected response
        }
    }

}

add_action( 'add_attachment', 'ai_auto_alt_media_upload_hook' );

// Register settings and fields
function ai_auto_alt_register_settings() {
    register_setting(
        PLUGIN_NAMESPACE . '_options_group', // Option group
        PLUGIN_NAMESPACE . '_settings',     // Option name
        'ai_auto_alt_settings_validate'     // Validation callback function
    );

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

    add_settings_section(
        PLUGIN_NAMESPACE . '_advanced_settings_section',
        'AI Auto Alt Advanced Settings',
        'ai_auto_alt_advanced_settings_section_cb',
        PLUGIN_NAMESPACE
    );

    add_settings_field(
        'ai_auto_alt_images_md_path',
        'Images Markdown Path',
        'ai_auto_alt_images_md_path_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_advanced_settings_section',
        array('label_for' => 'ai_auto_alt_images_md_path')
    );

    add_settings_field(
        'ai_auto_alt_openai_max_tokens',
        'OpenAI Max Tokens',
        'ai_auto_alt_openai_max_tokens_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_settings_section' // Assuming you're adding to the main settings section
    );


    add_settings_field(
        'ai_auto_alt_openai_temperature',
        'OpenAI Temperature',
        'ai_auto_alt_openai_temperature_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_settings_section' // Assuming you're adding to the main settings section
    );

    add_settings_field(
        'ai_auto_alt_openai_top_p',
        'OpenAI Top P',
        'ai_auto_alt_openai_top_p_cb',
        PLUGIN_NAMESPACE,
        PLUGIN_NAMESPACE . '_settings_section' // Assuming you're adding to the main settings section
    );

}

add_action('admin_init', 'ai_auto_alt_register_settings');

// Activation hook
function ai_auto_alt_activate() {
    // Set default settings
    $default_settings = array(
        'OPENAI_API_KEY' => '',
        'OPENAI_MODEL' => 'gpt-4-vision-preview',
        'MEDIA_ATTACHMENT_TYPES' => array('jpg', 'jpeg', 'png', 'gif', 'webp'),
        'OPENAI_PROMPT' => <<<EOD
        You are an expert in web development for the visually impaired. I am going to give you an image I want you to
        generate alternate text for. This is expressly to help visually impaired persons navigate the website,
        so you should focus on text that explains what the image does and the context of it, rather than long 
        verbose descriptions.

        You will receive the image filename, as well as any EXIF data that is available. You can 
        use this or ignore it as you see fit. Just make sure that the alt text is relevant to the image.
        Remember, you are the expert here.
        
        Please review your work before returning text.

        EOD,
        'AI_AUTO_ALT_LOCAL_DEBUG' => false,
        'AI_AUTO_ALT_IMAGES_MD_PATH' => '/var/www/html/wp-content/uploads/2020/01/images.md', // Default path setting
        'OPENAI_TEMPERATURE' => 0.7, // Suitable default value for 'temperature'
        'OPENAI_TOP_P' => 1.0, // Suitable default value for 'top_p'    
        'OPEN_AI_MAX_TOKENS' => 500 // Suitable default value for 'max_tokens
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

// Callback for the API key field
function ai_auto_alt_api_key_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_api_key" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_API_KEY]" type="text" value="' . esc_attr($options['OPENAI_API_KEY']) . '" class="regular-text" />';
    echo '<p class="description">Your OpenAI API key.</p>';
}

// Callback for the model field
function ai_auto_alt_model_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_model" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_MODEL]" type="text" value="' . esc_attr($options['OPENAI_MODEL']) . '" class="regular-text" />';
    echo '<p class="description">The specific OpenAI model to use (e.g., "gpt-4-vision-preview").</p>';
}

// Callback for the media attachment types field
function ai_auto_alt_media_types_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    $types = implode(', ', (array) $options['MEDIA_ATTACHMENT_TYPES']);
    echo '<input id="ai_auto_alt_media_types" name="' . PLUGIN_NAMESPACE . '_settings[MEDIA_ATTACHMENT_TYPES]" type="text" value="' . esc_attr($types) . '" class="regular-text" />';
    echo '<p class="description">Comma-separated list of media attachment types to process (e.g., jpg, jpeg, png).</p>';
}

// Callback for the OpenAI prompt field
function ai_auto_alt_openai_prompt_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    $prompt = $options['OPENAI_PROMPT'];
    echo '<textarea id="ai_auto_alt_openai_prompt" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_PROMPT]" rows="5" cols="50" class="large-text code">' . esc_textarea($prompt) . '</textarea>';
    echo '<p class="description">The prompt text sent to OpenAI API for generating alt text (customizable to guide the response).</p>';
}

// Callback for the OpenAI Temperature field
function ai_auto_alt_openai_temperature_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_openai_temperature" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_TEMPERATURE]" type="number" step="0.01" min="0" max="1" value="' . esc_attr($options['OPENAI_TEMPERATURE']) . '" class="regular-text" />';
    echo '<p class="description">Controls randomness: lower values make responses more deterministic, higher values more random (range 0-1).</p>';
}

// Callback for the OpenAI Top P field
function ai_auto_alt_openai_top_p_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_openai_top_p" name="' . PLUGIN_NAMESPACE . '_settings[OPENAI_TOP_P]" type="number" step="0.01" min="0" max="1" value="' . esc_attr($options['OPENAI_TOP_P']) . '" class="regular-text" />';
    echo '<p class="description">Controls diversity via nucleus sampling: 0.5 means half of all likelihood-weighted options are considered.</p>';
}

//Callback for the OpenAI Max Tokens field
function ai_auto_alt_openai_max_tokens_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_openai_max_tokens" name="' . PLUGIN_NAMESPACE . '_settings[OPEN_AI_MAX_TOKENS]" type="number" step="1" min="1" max="2048" value="' . esc_attr($options['OPEN_AI_MAX_TOKENS']) . '" class="regular-text" />';
    echo '<p class="description">The maximum number of tokens to generate (range 1-4096). 4096 would be excessive :P</p>';
}

function ai_auto_alt_local_debug_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    $local_debug_checked = isset($options['AI_AUTO_ALT_LOCAL_DEBUG']) ? 'checked' : '';
    echo '<input id="ai_auto_alt_local_debug" name="' . PLUGIN_NAMESPACE . '_settings[AI_AUTO_ALT_LOCAL_DEBUG]" type="checkbox" ' . $local_debug_checked . ' value="1">';
    echo '<p class="description">Enable local debugging mode to use test URLs from a markdown file.</p>';
}

function ai_auto_alt_images_md_path_cb() {
    $options = get_option(PLUGIN_NAMESPACE . '_settings');
    echo '<input id="ai_auto_alt_images_md_path" name="' . PLUGIN_NAMESPACE . '_settings[AI_AUTO_ALT_IMAGES_MD_PATH]" type="text" value="' . esc_attr($options['AI_AUTO_ALT_IMAGES_MD_PATH']) . '" class="regular-text" />';
    echo '<p class="description">The file path for the markdown file containing image URLs, used in local debugging mode.</p>';
}

// Define the callback function if you have an advanced settings section:
function ai_auto_alt_advanced_settings_section_cb() {
    echo '<p>Advanced settings for the AI Auto Alt plugin:</p>';
}

// Function to create settings page
function ai_auto_alt_settings_page() {
    add_options_page(
        'AI Auto Alt Settings',      // The text to be displayed in the title tags of the page when the menu is selected
        'AI Auto Alt',               // The text to be used for the menu
        'manage_options',            // The capability required for this menu to be displayed to the user
        PLUGIN_NAMESPACE,            // The slug name to refer to this menu by (should be unique for this menu)
        'ai_auto_alt_display_settings' // The function to be called to output the content for this page
    );
}

add_action('admin_menu', 'ai_auto_alt_settings_page');

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

// Validation callback function
function ai_auto_alt_settings_validate($input) {
    $new_input = array();

    $valid_models = array('gpt-4-vision-preview'); // Specify valid models
    
    // Validate OpenAI Model
    if (isset($input['OPENAI_MODEL'])) {
        $new_input['OPENAI_MODEL'] = sanitize_text_field($input['OPENAI_MODEL']);
        // Check if the model is in the list of known valid models
        if (!in_array($new_input['OPENAI_MODEL'], $valid_models)) {
            // Issue a warning that the model might not be correct
            add_settings_error(
                PLUGIN_NAMESPACE . '_settings',
                'possibly-invalid-model',
                'Warning: The specified OpenAI Model may not be supported or may not be updated in the plugin. If this model name is indeed correct and recently released, please disregard this message. Otherwise, double-check the model name.',
                'warning' // Set the type to "warning" instead of "error"
            );
        }
    } else {
        // If no model is provided, set a default model to avoid errors
        $new_input['OPENAI_MODEL'] = $valid_models[0];
        add_settings_error(
            PLUGIN_NAMESPACE . '_settings',
            'default-model-used',
            'No OpenAI Model provided. Using the default: ' . $valid_models[0] . '.',
            'info' // Set the type to "info" to indicate informative message
        );
    }

    // Validate OpenAI API Key
    if (isset($input['OPENAI_API_KEY'])) {
        $new_input['OPENAI_API_KEY'] = sanitize_text_field($input['OPENAI_API_KEY']);
        // API Key should be 54 characters long

        if (strlen($new_input['OPENAI_API_KEY']) != 54) {
            add_settings_error(
                PLUGIN_NAMESPACE . '_settings',
                'invalid-api-key',
                'OpenAI API Key must be 54 characters long.'
            );
        }

        //Check for alphanumeric characters, allow only a dash
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $new_input['OPENAI_API_KEY'])) {
            add_settings_error(
                PLUGIN_NAMESPACE . '_settings',
                'invalid-api-key',
                'OpenAI API Key must contain only alphanumeric characters and dashes.'
            );
        }
    }

    // Validate OpenAI Prompt
    if (isset($input['OPENAI_PROMPT'])) {
        $new_input['OPENAI_PROMPT'] = sanitize_textarea_field($input['OPENAI_PROMPT']);
        // Additional validation can be added if needed, for example, restrict the length
    }

    // Validate OpenAI Temperature
    if (isset($input['OPENAI_TEMPERATURE'])) {
        // Ensure input is a float between 0 and 1
        $new_input['OPENAI_TEMPERATURE'] = floatval(sanitize_text_field($input['OPENAI_TEMPERATURE']));
        if ($new_input['OPENAI_TEMPERATURE'] < 0 || $new_input['OPENAI_TEMPERATURE'] > 1) {
            add_settings_error(
                PLUGIN_NAMESPACE . '_settings',
                'invalid-temperature',
                'OpenAI Temperature must be between 0 and 1.'
            );
        }
    }

    // Validate OpenAI Top P
    if (isset($input['OPENAI_TOP_P'])) {
        // Ensure input is a float between 0 and 1
        $new_input['OPENAI_TOP_P'] = floatval(sanitize_text_field($input['OPENAI_TOP_P']));
        if ($new_input['OPENAI_TOP_P'] < 0 || $new_input['OPENAI_TOP_P'] > 1) {
            add_settings_error(
                PLUGIN_NAMESPACE . '_settings',
                'invalid-top_p',
                'OpenAI Top P must be between 0 and 1.'
            );
        }
    }

    return $new_input;
}