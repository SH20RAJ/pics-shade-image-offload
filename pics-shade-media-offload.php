<?php
/*
Plugin Name: Pics Shade Image Offload + Optimize + Resize
Plugin URI: https://docs.pics.shade.cool/wordpress-plugin
Description: Offload media from WordPress to Pics Shade - Image Hosting Made Easy.
Version: 1.0
Author: Shashwat Raj
Author URI: https://shade.cool
License: GPL2
Text Domain: pics-shade-media-offload
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Create admin menu
add_action('admin_menu', 'psio_add_admin_menu');

function psio_add_admin_menu() {
    add_options_page(
        'Pics Shade Image Offload + Optimize + Resize',
        'Pics Shade Image Offload',
        'manage_options',
        'psio_settings',
        'psio_settings_page'
    );
}

// Register settings
add_action('admin_init', 'psio_settings_init');

function psio_settings_init() {
    register_setting('psio_settings', 'psio_api_key');
    register_setting('psio_settings', 'psio_delete_after_upload');
    register_setting('psio_settings', 'psio_use_optimized_link');
    register_setting('psio_settings', 'psio_default_tags');

    add_settings_section('psio_section', __('API Settings', 'psio'), null, 'psio_settings');

    add_settings_field('psio_api_key', __('Pics Shade API Key', 'psio'), 'psio_api_key_render', 'psio_settings', 'psio_section');
    add_settings_field('psio_delete_after_upload', __('Delete Local Image After Upload', 'psio'), 'psio_delete_after_upload_render', 'psio_settings', 'psio_section');
    add_settings_field('psio_use_optimized_link', __('Use Optimized CDN Link', 'psio'), 'psio_use_optimized_link_render', 'psio_settings', 'psio_section');
    add_settings_field('psio_default_tags', __('Default Tags for Uploads', 'psio'), 'psio_default_tags_render', 'psio_settings', 'psio_section');
    add_settings_field('psio_docs_links', __('API Documentation Links', 'psio'), 'psio_docs_links_render', 'psio_settings', 'psio_section');
}

function psio_api_key_render() {
    $api_key = get_option('psio_api_key');
    echo '<input type="text" name="psio_api_key" value="' . esc_attr($api_key) . '" />';
}

function psio_delete_after_upload_render() {
    $delete_after_upload = get_option('psio_delete_after_upload');
    $checked = $delete_after_upload ? 'checked' : '';
    echo '<input type="checkbox" name="psio_delete_after_upload" ' . esc_attr($checked) . ' />';
}

function psio_use_optimized_link_render() {
    $use_optimized_link = get_option('psio_use_optimized_link');
    $checked = $use_optimized_link ? 'checked' : '';
    echo '<input type="checkbox" name="psio_use_optimized_link" ' . esc_attr($checked) . ' />';
}

function psio_default_tags_render() {
    $default_tags = get_option('psio_default_tags');
    echo '<input type="text" name="psio_default_tags" value="' . esc_attr($default_tags) . '" />';
}

function psio_docs_links_render() {
    echo '<p><a href="https://docs.pics.shade.cool/api-reference/get-api-key" target="_blank" rel="noopener noreferrer">Get API Key</a></p>';
    echo '<p><a href="https://docs.pics.shade.cool/api-reference/dashboard" target="_blank" rel="noopener noreferrer">Track Usage on Dashboard</a></p>';
    echo '<p><a href="https://docs.pics.shade.cool/" target="_blank" rel="noopener noreferrer">API Documentation</a></p>';
    echo '<p><a href="https://docs.pics.shade.cool/terms-of-service" target="_blank" rel="noopener noreferrer">Terms of Service</a></p>';
    echo '<p><a href="https://docs.pics.shade.cool/privacy-policy" target="_blank" rel="noopener noreferrer">Privacy Policy</a></p>';
    echo '<p><a href="https://pics.shade.cool" target="_blank" rel="noopener noreferrer">Visit PicsShade</a></p>';
}

function psio_settings_page() {
    ?>
    <div class="wrap">
        <h1>Pics Shade Image Offload + Optimize + Resize</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('psio_settings');
            do_settings_sections('psio_settings');
            submit_button();
            ?>
        </form>
    </div>
    <style>
        .wrap h1 {
            color: #0073aa;
        }
        .wrap form {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .wrap form p {
            margin: 10px 0;
        }
        .wrap form input[type="text"] {
            width: 100%;
            max-width: 400px;
        }
    </style>
    <?php
}

// Hook into the media upload process
add_filter('wp_handle_upload', 'psio_handle_upload');

function psio_handle_upload($upload) {
    if (isset($upload['type']) && strpos($upload['type'], 'image') !== false) {
        $api_key = get_option('psio_api_key');
        $image_path = $upload['file'];
        $delete_after_upload = get_option('psio_delete_after_upload');
        $default_tags = get_option('psio_default_tags');

        $image_url = psio_upload_to_pics_shade($image_path, $api_key, $default_tags);

        if ($image_url) {
            $upload['url'] = $image_url;
            $upload['file'] = null; // Clear the local file path

            if ($delete_after_upload) {
                // Delete the local file
                wp_delete_file($image_path);
            }
        }
    }
    return $upload;
}

function psio_upload_to_pics_shade($image_path, $api_key, $default_tags) {
    $url = 'https://pics.shade.cool/api/upload';
    
    // Extract just the file name from the full path
    $file_name = basename($image_path);
    $file_data = new CURLFile($image_path, 'image/jpeg', $file_name);

    $data = [
        'file' => $file_data,
        'path' => 'wp-uploads',
        'tags' => $default_tags,
    ];

    $headers = [
        'Authorization: Bearer ' . $api_key,
    ];

    $response = wp_safe_remote_post($url, [
        'headers' => $headers,
        'body' => $data,
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);

    if (get_option('psio_use_optimized_link')) {
        return $result['cdn'] ?? false;
    } else {
        return $result['url'] ?? false;
    }
}
?>
