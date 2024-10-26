<?php
/**
 * Plugin Name: Custom Font Plugin
 * Description: A plugin to manage custom fonts with upload functionality.
 * Version: 1.0
 * Author: Ali Hasan
 */


function enqueue_styles() {
    wp_register_style( 'custom-font-css', plugin_dir_url(__FILE__) . './public/css/custom-font-manager.css', false, '1.0.0' );
    wp_enqueue_style( 'custom-font-css' );
}

add_action( 'admin_enqueue_scripts', 'enqueue_styles' );


// Register Custom Post Type
function custom_font() {
    register_post_type('font', array(
        'labels' => array(
            'name' => 'Fonts',
            'singular_name' => 'Font',
            'add_new' => 'Add New Font',
            'add_new_item' => 'Add New Font',
            'edit_item' => 'Edit Font',
            'view_item' => 'View Font',
            'not_found' => 'Sorry, no fonts have been added.',
        ),
        'menu_icon' => 'dashicons-editor-bold',
        'public' => true,
        'show_in_menu' => true,
        'supports' => array('title'),
        'has_archive' => false,
    ));
}
add_action('init', 'custom_font');

// Allow additional MIME types for font uploads
function custom_allow_font_mime_types($mimes) {
    $mimes['woff'] = 'font/woff';
    $mimes['woff2'] = 'font/woff2';
    $mimes['ttf'] = 'font/ttf';
    $mimes['svg'] = 'image/svg+xml';
    $mimes['eot'] = 'application/vnd.ms-fontobject';
    return $mimes;
}
add_filter('upload_mimes', 'custom_allow_font_mime_types');

// Enqueue Media Uploader
function custom_font_enqueue_media_uploader() {
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'custom_font_enqueue_media_uploader');

// Add Metabox
function custom_font_metabox() {
    add_meta_box(
        'custom_font_upload',
        'Manage Your Font Files',
        'custom_font_metabox_callback',
        'font',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'custom_font_metabox');

// Metabox Callback
function custom_font_metabox_callback($post) {
    wp_nonce_field('custom_font_nonce', 'custom_font_nonce_field');

    $font_urls = array(
        'woff' => get_post_meta($post->ID, '_custom_font_woff', true),
        'woff2' => get_post_meta($post->ID, '_custom_font_woff2', true),
        'ttf' => get_post_meta($post->ID, '_custom_font_ttf', true),
        'svg' => get_post_meta($post->ID, '_custom_font_svg', true),
        'eot' => get_post_meta($post->ID, '_custom_font_eot', true),
    );

    $font_weight = get_post_meta($post->ID, '_custom_font_weight', true) ?: 'normal';
    $font_style = get_post_meta($post->ID, '_custom_font_style', true) ?: 'normal';
    echo '<label>Weight:</label><select name="custom_font_weight" id="custom_font_weight">';
    $weights = ['normal', '100', '200', '300', '400', '500', '600', '700', '800', '900', 'bold'];
    foreach ($weights as $weight) {
        echo '<option value="' . $weight . '" ' . selected($font_weight, $weight, false) . '>' . ucfirst($weight) . '</option>';
    }
    echo '</select>';

    echo '<label>Style:</label><select name="custom_font_style" id="custom_font_style">';
    $styles = ['normal', 'italic', 'oblique'];
    foreach ($styles as $style) {
        echo '<option value="' . $style . '" ' . selected($font_style, $style, false) . '>' . ucfirst($style) . '</option>';
    }
    echo '</select><hr><div id="font-files-container">';

    foreach ($font_urls as $type => $url) {
        echo '<div class="font-upload-group">';
        echo '<label class="custom-font-label" for="custom_font_' . $type . '">' . strtoupper($type) . ' Font:</label><br>';
        echo '<div class="font-upload-input-wrapper">';
        echo '<input type="text" id="custom_font_' . $type . '" name="custom_font_' . $type . '" value="' . esc_attr($url) . '" style="width: 80%;" />';
        echo '<button class="button upload_custom_font_button" data-type="' . $type . '" ' . ($url ? 'style="display:none;"' : '') . '>Upload Font</button>';
        echo '<button class="button delete_font_group" ' . ($url ? '' : 'style="display:none;"') . '>Delete</button><br><br>';
        echo '</div>';
        echo '<div id="font-preview-' . $type . '"></div><hr></div>';
    }
    echo '<div id="additional-font-files-container"></div>';
    echo '<button class="button" id="add_font_group">Add Font Variations</button></div>';
}

// Save font meta
function save_custom_font_meta($post_id) {
    if (!isset($_POST['custom_font_nonce_field']) || !wp_verify_nonce($_POST['custom_font_nonce_field'], 'custom_font_nonce')) {
        return;
    }

    $font_types = ['woff', 'woff2', 'ttf', 'svg', 'eot'];
    foreach ($font_types as $type) {
        if (isset($_POST['custom_font_' . $type])) {
            update_post_meta($post_id, '_custom_font_' . $type, sanitize_text_field($_POST['custom_font_' . $type]));
        }
    }

    if (isset($_POST['custom_font_weight'])) {
        update_post_meta($post_id, '_custom_font_weight', sanitize_text_field($_POST['custom_font_weight']));
    }

    if (isset($_POST['custom_font_style'])) {
        update_post_meta($post_id, '_custom_font_style', sanitize_text_field($_POST['custom_font_style']));
    }
}
add_action('save_post', 'save_custom_font_meta');

// JavaScript for media uploader and add/delete field groups
function custom_font_upload_script() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            var file_frame;

            // Upload font button functionality
            $(document).on('click', '.upload_custom_font_button', function (event) {
                event.preventDefault();
                var button = $(this);
                var expectedType = button.data('type'); // Get the expected file type

                file_frame = wp.media({
                    title: 'Select a Font',
                    button: { text: 'Use this font' },
                    multiple: false
                });

                file_frame.on('select', function () {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    var inputField = button.siblings('input[type="text"]');
                    var fileExtension = attachment.filename.split('.').pop().toLowerCase(); // Get the file extension

                    // Check if the file extension matches the expected type
                    if (fileExtension !== expectedType) {
                        alert('Invalid file type! Please upload a .' + expectedType + ' file.');
                        return;
                    }

                    inputField.val(attachment.url);
                    button.hide();
                    button.siblings('.delete_font_group').show();
                });

                file_frame.open();
            });

            // Delete font input field content
            $(document).on('click', '.delete_font_group', function (event) {
                event.preventDefault();
                var button = $(this);
                var inputField = button.siblings('input[type="text"]');

                inputField.val('');
                button.hide();
                button.siblings('.upload_custom_font_button').show();
            });

            // Add 5 new font input fields to new div container with a delete button for the group
            $('#add_font_group').on('click', function (event) {
                event.preventDefault();
                var newGroupContainer = $('<div class="font-group-set"></div>');

                for (var i = 0; i < 5; i++) {
                    var newGroup = $('.font-upload-group').first().clone();
                    newGroup.find('input').val('');
                    newGroup.find('.delete_font_group').hide();
                    newGroup.find('.upload_custom_font_button').show();
                    newGroupContainer.append(newGroup);
                }

                newGroupContainer.append('<button class="button delete_group_set">Delete Group</button><hr>');
                $('#additional-font-files-container').append(newGroupContainer);
            });

            // Delete the entire group of 5 font input fields
            $(document).on('click', '.delete_group_set', function (event) {
                event.preventDefault();
                $(this).parent('.font-group-set').remove();
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'custom_font_upload_script');
