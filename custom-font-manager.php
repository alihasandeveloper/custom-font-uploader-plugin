<?php
/*
Plugin Name: Custom Font Upload
Description: A plugin to manage custom font uploads.
Version: 1.0
Author: Ali Hasan
*/

class CustomFontUploadPlugin {
    public function __construct() {
        add_action('init', [$this, 'register_custom_post_type']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles_and_media']);
        add_action('add_meta_boxes', [$this, 'add_font_metabox']);
        add_action('save_post', [$this, 'save_custom_font_meta']);
        add_filter('upload_mimes', [$this, 'allow_font_mime_types']);
        add_action('admin_footer', [$this, 'font_upload_script']);
    }

    // Enqueue CSS and media uploader
    public function enqueue_styles_and_media() {
        wp_register_style('custom-font-css', plugin_dir_url(__FILE__) . 'public/css/custom-font-manager.css', false, '1.0.0');
        wp_enqueue_style('custom-font-css');
        wp_enqueue_media();
    }

    // Register Custom Post Type for Fonts
    public function register_custom_post_type() {
        register_post_type('font', [
            'labels' => [
                'name' => 'Fonts',
                'singular_name' => 'Font',
                'add_new' => 'Add New Font',
                'add_new_item' => 'Add New Font',
                'edit_item' => 'Edit Font',
                'view_item' => 'View Font',
                'not_found' => 'Sorry, no fonts have been added.',
            ],
            'menu_icon' => 'dashicons-editor-bold',
            'public' => true,
            'show_in_menu' => true,
            'supports' => ['title'],
            'has_archive' => false,
        ]);
    }

    // Allow additional MIME types for font uploads
    public function allow_font_mime_types($mimes) {
        $mimes['woff'] = 'font/woff';
        $mimes['woff2'] = 'font/woff2';
        $mimes['ttf'] = 'font/ttf';
        $mimes['svg'] = 'image/svg+xml';
        $mimes['eot'] = 'application/vnd.ms-fontobject';
        return $mimes;
    }

    // Add metabox for font variations
    public function add_font_metabox() {
        add_meta_box(
            'custom_font_upload',
            'Manage Your Font Files',
            [$this, 'render_metabox'],
            'font',
            'normal',
            'high'
        );
    }

    // Render the metabox for font variations
    public function render_metabox($post) {
        wp_nonce_field('custom_font_nonce', 'custom_font_nonce_field');

        $font_variations = get_post_meta($post->ID, '_custom_font_variations', true) ?: [];

        echo '<div id="font-variations-container">';
        foreach ($font_variations as $index => $variation) {
            $this->display_font_variation_group($index, $variation);
        }
        echo '</div>';
        echo '<button class="button" id="add_font_group">Add Font Variation</button>';
    }

    // Display font variation group fields
    private function display_font_variation_group($index, $variation = []) {
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

    // Save font variations metadata
    public function save_custom_font_meta($post_id) {
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

    // Enqueue JavaScript for handling font upload and UI interactions
    public function font_upload_script() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                let groupIndex = <?php echo json_encode(count(get_post_meta(get_the_ID(), '_custom_font_variations', true) ?: [])); ?>;

                $('#add_font_group').on('click', function (event) {
                    event.preventDefault();
                    let newGroupHtml = `<div class="repeater_row" data-index="${groupIndex}">
                            <div class="font-upload-group"> ... </div>
                        </div>`;
                    $('#font-variations-container').append(newGroupHtml);
                    groupIndex++;
                });

                $(document).on('click', '.upload_custom_font_button', function (event) {
                    event.preventDefault();
                    let button = $(this);
                    let input = button.siblings('input[type="text"]');
                    let fileType = button.data('type');

                    let file_frame = wp.media({
                        title: 'Select a ' + fileType.toUpperCase() + ' Font',
                        button: { text: 'Use this font' },
                        multiple: false
                    });

                    file_frame.on('select', function () {
                        let attachment = file_frame.state().get('selection').first().toJSON();
                        let fileExtension = attachment.filename.split('.').pop().toLowerCase();

                        if (fileExtension !== fileType) {
                            alert('Invalid file type! Please upload a .' + fileType + ' file.');
                            return;
                        }

                        input.val(attachment.url);
                        button.hide();
                        button.siblings('.remove_font_button').show();
                    });

                    file_frame.open();
                });

                $(document).on('click', '.remove_font_button', function (event) {
                    event.preventDefault();
                    let button = $(this);
                    let input = button.siblings('input[type="text"]');

                    input.val('');
                    button.hide();
                    button.siblings('.upload_custom_font_button').show();
                });
            });
        </script>
        <?php
    }
}

new CustomFontUploadPlugin();
?>
