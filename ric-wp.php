<?php

/*
  Plugin Name: RIC - Responsive Image Cache
  Plugin URI: https://github.com/phzfi/RIC
  Description: This is a Responsive Image Cache for wordpress.
  Version: 1.0
  Author: PHZ
  Author URI: https://phz.fi/
  License: GPLv2+
  Text Domain: wp-ric
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ric-wp-activator.php
 */
function activate_ric_wp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ric-wp-activator.php';
	Ric_Wp_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ric-wp-deactivator.php
 */
function deactivate_ric_wp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ric-wp-deactivator.php';
	Ric_Wp_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ric_wp' );
register_deactivation_hook( __FILE__, 'deactivate_ric_wp' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ric-wp.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ric_wp() {

	$plugin = new Ric_Wp();
	$plugin->run();

}

/**
 * Main functionality of the plugin, i.e. to load client.js script that does
 * the actual work
 */

class Ric_Settings
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin',
            'RIC Settings',
            'manage_options',
            'ric-settings-admin',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        $this->options = get_option( 'ric-setting-group' )
        ?>
        <div class="wrap">
            <h2>RIC Settings</h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'ric-setting-group' );
                do_settings_sections( 'ric-settings-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'ric-setting-group', // Option group
            'ric-setting-group', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'ric_section', // ID
            'RIC server URL settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'ric-settings-admin' // Page
        );

        add_settings_field(
            'url',
            'RIC Server URL',
            array(  $this, 'url_callback' ),
            'ric-settings-admin',
            'ric_section'
        );


    }

    /**
     * @param $input
     * @return array
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['url'] ) )
            $new_input['url'] = sanitize_text_field( $input['url'] );
        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your URL below:';
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function url_callback()
    {
      printf(
        '<input type="text" id="url" name="ric-setting-group[url]" value="%s" />',
        isset( $this->options['url'] ) ? esc_attr( $this->options['url']) : ''
      );
    }
}

if (is_admin()) {
	$settings = new Ric_Settings();
}

function disable_srcset($sources) { return false; }

function load_js_file()
{
    wp_enqueue_script('image-loader', plugins_url('/image-loader.js', __FILE__));
}

function ric_src_the_post($post_object) {
    if(empty($post_object->post_content)) {
        return $post_object;
    }
    //Disable DOM error reporting
    libxml_use_internal_errors(true);
    $post = new DOMDocument();
    $post->loadHTML($post_object->post_content);
    $images = $post->getElementsByTagName('img');

    // Iterate each img tag
    foreach ($images as $image) {
        $src = $image->getAttribute('src');
        if(ric_already_encoded($src)) {
            continue;
        }

        $width = $image->getAttribute('width');
        $height = $image->getAttribute('height');
        $new_src = ric_encode_url($src);

        if (@getimagesize($new_src)) {

            $image->removeAttribute('src');
            $image->setAttribute('data-src', $new_src);
            if ($width !== "") {
                $image->removeAttribute('width');
                $image->setAttribute('data-width', $width);
            }
            if ($height !== "") {
                $image->removeAttribute('height');
                $image->setAttribute('data-height', $height);
            }
        }
    };

    $post_object->post_content = $post->saveHTML();
    return $post_object;
}

function ric_src_the_content($page_content) {
    if(empty($page_content)) {
        return $page_content;
    }

    //Disable DOM error reporting
    libxml_use_internal_errors(true);
    $post = new DOMDocument();
    $post->loadHTML($page_content);
    $images = $post->getElementsByTagName('img');

    // Iterate each img tag
    foreach ($images as $image) {
        $src = $image->getAttribute('src');
        if(ric_already_encoded($src)) {
            continue;
        }

        $width = $image->getAttribute('width');
        $height = $image->getAttribute('height');
        $new_src = ric_encode_url($src);

        if (@getimagesize($new_src)) {

            $image->removeAttribute('src');
            $image->setAttribute('data-src', $new_src);
            if ($width !== "") {
                $image->removeAttribute('width');
                $image->setAttribute('data-width', $width);
            }
            if ($height !== "") {
                $image->removeAttribute('height');
                $image->setAttribute('data-height', $height);
            }
        }
    };

    return $post->saveHTML();
}

function ric_already_encoded($src) {
    $ric_options = get_option('ric-setting-group');
    $ric_url = $ric_options['url'];

    return (strpos($src, $ric_url) === 0);
}

function ric_encode_url($url) {
    //TODO: Remove url params?
    $ric_options = get_option('ric-setting-group');
    $ric_url = $ric_options['url'];

    return $ric_url . '/' . base64_encode($url);
}

function ric_wp_get_attachment_url($src) {
    //TODO: Check if dimensions are usable from here
//    $attachmentMeta = wp_get_attachment_metadata($id);
    if(ric_already_encoded($src)) {
        return $src;
    }

    $new_src = ric_encode_url($src);
    if (@getimagesize($new_src)) {
        $src = $new_src;
    }
    return $src;
}

function ric_wp_get_attachment_image_src($parameters) {
    //TODO: Check if dimensions are usable from here
//    $attachmentMeta = wp_get_attachment_metadata($id);

    list($src) = $parameters;
    if(ric_already_encoded($src)) {
        return $parameters;
    }

    $new_src = ric_encode_url($src);
    if (@getimagesize($new_src)) {
        $parameters[0] = $new_src;
    }

    return $parameters;
}

function ric_delete_attachment($id) {

    $post_type = get_post_mime_type($id);
    if(strpos($post_type, "image/") === false) {
        return;
    }
    /* TODO: We must send something to the server to authenticate the request.
             Otherwise RIC is vulnerable to DoSsing */


    $url =  wp_get_attachment_url( $id );
    if (!ric_already_encoded($url)) {
        $url = ric_encode_url($url);
    }

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded",
            'method'  => 'DELETE'
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    //TODO: React to code?
    $code = getHttpCode($http_response_header);

    return $result;
}

function ric_get_header_image_tag($img) {
    $document = new DOMDocument();
    $document->loadHTML($img, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    $images = $document->getElementsByTagName('img');

    // Iterate each img tag
    foreach ($images as $image) {
        $src = $image->getAttribute('src');
        $new_src = ric_encode_url($src);
        if (@getimagesize($new_src)) {
            $src = $new_src;
            $image->setAttribute('data-src', $src);
            $image->removeAttribute('src');
        }
    }

    return $document->saveHTML();
}

function getHttpCode($http_response_header)
{
    if(is_array($http_response_header))
    {
        $parts=explode(' ',$http_response_header[0]);
        if(count($parts)>1) //HTTP/1.0 <code> <text>
            return intval($parts[1]); //Get code
    }
    return 0;
}


if(!!is_admin() === false) {
    add_action('wp_head', 'load_js_file');
    add_filter('wp_calculate_image_srcset', 'disable_srcset');

    add_filter('the_content', 'ric_src_the_content', 15);  // hook into filter and use priority 15 to make sure it is run after the srcset and sizes attributes have been added.
    add_action('the_post', 'ric_src_the_post', 15);
    add_filter('wp_get_attachment_url', 'ric_wp_get_attachment_url' , 15);
    add_filter('wp_get_attachment_image_src', 'ric_wp_get_attachment_image_src' , 10);
    add_filter('get_header_image_tag', 'ric_get_header_image_tag' , 15);
//    add_filter('get_header_image', 'ric_get_header_image_src' , 15);
} else {
    add_filter('delete_attachment', 'ric_delete_attachment', 5);
}
run_ric_wp();

?>
