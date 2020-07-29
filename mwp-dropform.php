<?php

/**
 * Plugin Name:       MWP Dropzone Uploader
 * Plugin URI:        https://mwp-development.com
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            MWP Development
 * Author URI:        https://mwp-development.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mwp-dropform
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin constants.
 */
if (!defined('MWP_DROPFORM_VERSION')) {
    define('MWP_DROPFORM_VERSION', '1.0.0'); // Plugin version.
}

if (!defined('MWP_DROPFORM_PATH')) {
    define('MWP_DROPFORM_PATH', plugin_dir_path(__FILE__)); // Plugin Folder Path.
}

/**
 * MWP_Dropform
 */
class MWP_Dropform
{

    /**
     * Static property to hold our singleton instance
     *
     */
    static $instance = false;

    /**
     * This is our constructor
     *
     * @return void
     */
    private function __construct()
    {
        // back end
        add_action('plugins_loaded', array($this, 'textdomain'));
        add_action('wp_ajax_mwp_dropform_upload_handler', array($this, 'handle_form_data')); // for logged in users
        add_action('wp_ajax_mwp_dropform_delete_file', array($this, 'delete_file')); // for logged in users

        // front end
        add_action('wp_enqueue_scripts', array($this, 'enqueue_front_styles'), 10);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_front_scripts'), 10);
        add_shortcode('mwp_dropform', array($this, 'form_shortcode'), 10, 2);

    }

    /**
     * If an instance exists, this returns it.  If not, it creates one and
     * retuns it.
     *
     * @return MWP_Dropform
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * load textdomain
     *
     * @return void
     */
    public function textdomain()
    {
        load_plugin_textdomain('mwp-dropform', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Call front-end JS
     *
     * @return void
     */
    public function enqueue_front_scripts()
    {
        wp_enqueue_script('mwp-dropform-dropzone', plugin_dir_url(__FILE__) . 'assets/js/dropzone.min.js', array('jquery'), MWP_DROPFORM_VERSION, true);

        wp_enqueue_script('mwp-dropform', plugin_dir_url(__FILE__) . 'assets/js/mwp-dropform.js', array('jquery'), MWP_DROPFORM_VERSION, true);
        wp_localize_script('mwp-dropform', 'mwp_dropform_cntrl', array(
            'upload_file' => admin_url('admin-ajax.php?action=mwp_dropform_upload_handler'),
            'delete_file' => admin_url('admin-ajax.php?action=mwp_dropform_delete_file'),
        ));
    }

    /**
     * Call front-end CSS
     *
     * @return void
     */
    public function enqueue_front_styles()
    {
        wp_enqueue_style('mwp-dropform-dropzone', plugin_dir_url(__FILE__) . 'assets/css/dropzone.min.css', array(), MWP_DROPFORM_VERSION, 'all');
        wp_enqueue_style('mwp-dropform', plugin_dir_url(__FILE__) . 'assets/css/mwp-dropform.css', array(), MWP_DROPFORM_VERSION, 'all');
    }

    /**
     * Callback method for displaying our form using shortcode
     *
     * @param  array $atts
     * @param  string $content
     * @return void
     */
    public function form_shortcode($atts, $content = null)
    {
        $form_html = '';

        if ( is_user_logged_in() ) { // show field only for logged in users
            $form_html .= '<div id="mwp-dropform-wrapper">';
            $form_html .= '<div id="mwp-dropform-uploder" class="dropzone"></div>'; // element for dropzone field
            $form_html .= wp_nonce_field('mwp_dropform_register_ajax_nonce', 'mwp-dropform-nonce', true, false); // returns security nonce field
            $form_html .= '</div>';
        }

        return $form_html;
    }

    /**
     * Handle form data received from frontend via AJAX
     *
     * @return void
     */
    public function handle_form_data()
    {
        if (isset($_POST['mwp-dropform-nonce'])
            && wp_verify_nonce($_POST['mwp-dropform-nonce'], 'mwp_dropform_register_ajax_nonce')
        ) {
            if (!empty($_FILES)) {

                // These files need to be included as dependencies when on the front end.
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/media.php' );

                foreach ($_FILES as $file => $array) {
                    if ($_FILES[$file]['error'] !== UPLOAD_ERR_OK) { // If there is some errors, during file upload
                        wp_send_json(array('status' => 'error', 'message' => __('Error: ', 'mwp-dropform') . $_FILES[$file]['error']));
                    }

                    // HANDLE RECEIVED FILE

                    $post_id = 0; // Set post ID to attach uploaded image to specific post

                    $attachment_id = media_handle_upload($file, $post_id);

                    if (is_wp_error($attachment_id)) { // Check for errors during attachment creation
                        wp_send_json(array(
                            'status' => 'error',
                            'message' => __('Error while processing file', 'mwp-dropform'),
                        ));
                    } else {
                        wp_send_json(array(
                            'status' => 'ok',
                            'attachment_id' => $attachment_id,
                            'message' => __('File uploaded', 'mwp-dropform'),
                        ));
                    }
                }
            }
            wp_send_json(array('status' => 'error', 'message' => __('There is nothing to upload!', 'mwp-dropform')));
        }
        wp_send_json(array('status' => 'error', 'message' => __('Security check failed!', 'mwp-dropform')));
    }

    /**
     * Delete attachment by id via AJAX
     *
     * @return void
     */
    public function delete_file()
    {

        if (isset($_POST['attachment_id']) 
            && isset($_POST['mwp-dropform-nonce'])
            && wp_verify_nonce($_POST['mwp-dropform-nonce'], 'mwp_dropform_register_ajax_nonce')
        ) {
            $attachment_id = absint($_POST['attachment_id']);

            $result = wp_delete_attachment($attachment_id, true); // permanently delete attachment

            if ($result) {
                wp_send_json(array('status' => 'ok'));
            }
        }
        wp_send_json(array('status' => 'error'));
    }
}
// end class

// Instantiate our class
$MWP_Dropform = MWP_Dropform::getInstance();
