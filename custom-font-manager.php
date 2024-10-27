<?php
/*
Plugin Name: Custom Font Upload
Description: A plugin to manage custom font uploads.
Version: 1.0
Author: Ali Hasan
*/

function enqueue_styles()
{
    wp_register_style('custom-font-css', plugin_dir_url(__FILE__) . './public/css/custom-font-manager.css', false, '1.0.0');
    wp_enqueue_style('custom-font-css');
}

add_action('admin_enqueue_scripts', 'enqueue_styles');

// Register Custom Post Type
function custom_font()
{
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

// Enqueue media uploader for admin pages
function custom_font_enqueue_media_uploader()
{
    wp_enqueue_media();
}

add_action('admin_enqueue_scripts', 'custom_font_enqueue_media_uploader');

// Add Metabox
function custom_font_metabox()
{
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
function custom_font_metabox_callback($post)
{
    wp_nonce_field('custom_font_nonce', 'custom_font_nonce_field');

    // Load existing font variations
    $font_variations = get_post_meta($post->ID, '_custom_font_variations', true) ?: [];

    echo '<div id="font-variations-container">';

    foreach ($font_variations as $index => $variation) {
        display_font_variation_group($index, $variation);
    }

    echo '</div>';
    echo '<button class="button" id="add_font_group">Add Font Variation</button>';
}

// Display font variation group
function display_font_variation_group($index, $variation = [])
{
    $font_types = ['woff', 'woff2', 'ttf', 'svg', 'eot'];

    echo '<div class="font-group" data-index="' . esc_attr($index) . '">';

    foreach ($font_types as $type) {
        $url = $variation[$type] ?? '';
        echo '<label>' . strtoupper($type) . ' URL:</label>';
        echo '<input type="text" name="custom_font_variations[' . esc_attr($index) . '][' . esc_attr($type) . ']" value="' . esc_url($url) . '" style="width: 80%;" />';
        echo '<button class="button upload_custom_font_button" data-type="' . $type . '" data-index="' . esc_attr($index) . '">Upload</button>';
        echo '<button class="button remove_font_button" data-index="' . esc_attr($index) . '" ' . ($url ? '' : 'style="display:none;"') . '>Remove</button><br>';
    }

    echo '</div>';
}

// Save font variations
function save_custom_font_meta($post_id)
{
    if (!isset($_POST['custom_font_nonce_field']) || !wp_verify_nonce($_POST['custom_font_nonce_field'], 'custom_font_nonce')) {
        return;
    }

    if (isset($_POST['custom_font_variations'])) {
        $variations = array_map(function ($variation) {
            return [
                'woff' => sanitize_text_field($variation['woff'] ?? ''),
                'woff2' => sanitize_text_field($variation['woff2'] ?? ''),
                'ttf' => sanitize_text_field($variation['ttf'] ?? ''),
                'svg' => sanitize_text_field($variation['svg'] ?? ''),
                'eot' => sanitize_text_field($variation['eot'] ?? ''),
            ];
        }, $_POST['custom_font_variations']);

        update_post_meta($post_id, '_custom_font_variations', $variations);
    } else {
        delete_post_meta($post_id, '_custom_font_variations');
    }
}

add_action('save_post', 'save_custom_font_meta');

function custom_font_upload_script()
{
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            let groupIndex = <?php echo json_encode(count(get_post_meta(get_the_ID(), '_custom_font_variations', true) ?: [])); ?>;

            // Add new font variation group
            $('#add_font_group').on('click', function (event) {
                event.preventDefault();

                let newGroupHtml = `<div class="repeater_row" data-index="${groupIndex}">
                        <div class="close" data-index="${groupIndex}">
                            <div class="font-header-wrapper">
                                <div class="font-weight-wrapper">
                                    <label for="weight">Weight:</label>
                                    <select name="weight" id="weight">
                                        <option value="normal">Normal</option>
                                        <option value="100">100</option>
                                        <option value="400">400</option>
                                        <option value="bold">800</option>
                                    </select>
                                </div>
                                <div class="font-style-wrapper">
                                    <label for="style">Style:</label>
                                    <select name="style" id="" class="font_style">
                                        <option value="normal">Normal</option>
                                        <option value="italic">Italic</option>
                                        <option value="oblique">Oblique</option>
                                    </select>
                                </div>
                            </div>
                            <div class="font-title-wrapper">
                                <p>Frontis is Making Web Beautiful!!!</p>
                            </div>
                            <div class="font-button-wrapper">
                                 <button type="button" class="font_edit_button">Close</button>
                                <button  type="button" id="font_delete_button">Delete</button>
                            </div>
                        </div>
                        <div class="repeater_row_content" id="font-open-close" data-index="${groupIndex}">
                            <?php foreach (['woff', 'woff2', 'ttf', 'svg', 'eot'] as $type) { ?>
                                <div class="font-upload-group">
                                    <label><?php echo strtoupper($type); ?> URL:</label>
                                    <input type="text" name="custom_font_variations[${groupIndex}][<?php echo $type; ?>]" value="" />
                                    <button class="button upload_custom_font_button" data-type="<?php echo $type; ?>" data-index="${groupIndex}">Upload</button>
                                    <button class="button remove_font_button" data-index="${groupIndex}" style="display:none;">Remove</button>
                                </div>
                            <?php } ?>
                    <div>
                </div>`;

                $('#font-variations-container').append(newGroupHtml);
                groupIndex++;
            });

            // Upload font file and set URL for specific input only
            $(document).on('click', '.upload_custom_font_button', function (event) {
                event.preventDefault();

                var button = $(this);
                var input = button.siblings('input[type="text"]'); // Get the corresponding input
                var fileType = button.data('type'); // Get the file type for this button

                var file_frame = wp.media({
                    title: 'Select a ' + fileType.toUpperCase() + ' Font',
                    button: {text: 'Use this font'},
                    multiple: false // Set to false for single file upload
                });

                file_frame.on('select', function () {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    var fileExtension = attachment.filename.split('.').pop().toLowerCase();

                    // Check if the selected file matches the required type
                    if (fileExtension !== fileType) {
                        alert('Invalid file type! Please upload a .' + fileType + ' file.');
                        return;
                    }

                    input.val(attachment.url); // Set the URL in the corresponding input field
                    button.hide(); // Hide the upload button
                    button.siblings('.remove_font_button').show(); // Show the remove button
                });

                file_frame.open(); // Open the media frame
            });

            $(document).on('click', '.font_edit_button', function () {
                const index = $(this).data('index');
                const element = $('#font-open-close');
                // Toggle the 'font-open' class
                $(element).toggleClass('font-open');

                // Change button text based on the class
                if ($(element).hasClass('font-open')) {
                    $(this).text('Close');
                } else {
                    $(this).text('Open');
                }
            });


            $(document).on('click', '#font_delete_button', function () {
                const index = $(this).data('index');
                $(this).closest('.repeater_row').remove(); // Remove the row
            });


            // Remove URL and reset upload button for specific input
            $(document).on('click', '.remove_font_button', function (event) {
                event.preventDefault();
                var button = $(this);
                var input = button.siblings('input[type="text"]');

                input.val(''); // Clear the input value
                button.hide(); // Hide the remove button
                button.siblings('.upload_custom_font_button').show(); // Show the upload button again
            });
        });
    </script>
    <?php
}

add_action('admin_footer', 'custom_font_upload_script');


?>

<!--<div class="repeater_row">-->
<!--    <div class="close">-->
<!--        <div class="font-header-wrapper">-->
<!--            <div class="font-weight-wrapper">-->
<!--                <label for="weight">Weight:</label>-->
<!--                <select name="weight" id="weight">-->
<!--                    <option value="normal">Normal</option>-->
<!--                    <option value="100">100</option>-->
<!--                    <option value="400">400</option>-->
<!--                    <option value="bold">800</option>-->
<!--                </select>-->
<!--            </div>-->
<!--            <div class="font-style-wrapper">-->
<!--                <label for="style">Style:</label>-->
<!--                <select name="style" id="" class="font_style">-->
<!--                    <option value="normal">Normal</option>-->
<!--                    <option value="italic">Italic</option>-->
<!--                    <option value="oblique">Oblique</option>-->
<!--                </select>-->
<!--            </div>-->
<!--        </div>-->
<!--        <div class="font-title-wrapper">-->
<!--            <p>Frontis is Making Web Beautiful!!!</p>-->
<!--        </div>-->
<!--        <div class="font-button-wrapper">-->
<!--            <button id="edit_button">Edit</button>-->
<!--            <button id="delete_button">Delete</button>-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="repeater_row_content">-->
<!--        <div class="repeater_row_content_woff">-->
<!---->
<!--        </div>-->
<!--    </div>-->
<!--</div>-->

<!---->
<!--var newGroupHtml = `<div class="font-group" data-index="${groupIndex}">-->
<!--    --><?php //foreach (['woff', 'woff2', 'ttf', 'svg', 'eot'] as $type) { ?>
<!--        <label>--><?php //echo strtoupper($type); ?><!-- URL:</label>-->
<!--        <input type="text" name="custom_font_variations[${groupIndex}][--><?php //echo $type; ?><!--]" value="" style="width: 80%;" />-->
<!--        <button class="button upload_custom_font_button" data-type="--><?php //echo $type; ?><!--" data-index="${groupIndex}">Upload</button>-->
<!--        <button class="button remove_font_button" data-index="${groupIndex}" style="display:none;">Remove</button><br>-->
<!--    --><?php //} ?>
<!--</div>`;-->
<!---->
<!---->
<!---->
