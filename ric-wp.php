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

/**
 * @param mixed $sources
 * @return bool
 */
function disable_srcset($sources) { return false; }

function load_js_file()
{
    wp_enqueue_script('screen-check', plugins_url('/screen-check.js', __FILE__));
}

/**
 * @param object $post_object
 * @return string
 */
function ric_src_the_post($post_object) {
    if(empty($post_object->post_content)) {
        return $post_object;
    }
    return ric_replace_content_img_src($post_object->post_content);
}

/**
 * @param string $page_content
 * @return string
 */
function ric_src_the_content($page_content) {
    if(empty($page_content)) {
        return $page_content;
    }
    return ric_replace_content_img_src($page_content);
}

/**
 * @param string $content
 * @return string
 */
function ric_replace_content_img_src($content) {
    //Disable DOM error reporting
    libxml_use_internal_errors(true);
    $post = new DOMDocument();

    $post->loadHTML(utf8_decode($content));
    $images = $post->getElementsByTagName('img');

    // Iterate each img tag
    foreach ($images as $image) {
        $src = $image->getAttribute('src');
        if(strpos($src, 'data:') === 0) {
            continue; //embedded data
        }
        if(ric_already_encoded($src)) {
            continue;
        }

        $new_src = ric_encode_url($src);
        if (@getimagesize($new_src)) {
            $image->setAttribute('src', $new_src);
        }
    };
    return utf8_encode($post->saveHTML());
}

/**
 * @param string $src
 * @return boolean
 */
function ric_already_encoded($src) {
    $ric_options = get_option('ric-setting-group');
    $ric_url = $ric_options['url'];

    return (strpos($src, $ric_url) === 0);
}

/**
 * @param string $url
 * @return string
 */
function ric_encode_url($url) {
    if (ric_is_host_blacklisted($url)) {
        return $url;
    }
    //TODO: Remove url params?
    $ric_options = get_option('ric-setting-group');
    $ric_url = $ric_options['url'];

    $viewport = get_viewport_from_cookie();

    return $ric_url . '/' . base64_encode($url) . "?width=". $viewport["width"];
}

function ric_is_host_blacklisted($url) {

    $blacklistedHosts = ["gravatar.com"];

    foreach ($blacklistedHosts as $host) {
        if(strpos($url, $host) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param string $url
 * @return string
 */
function ric_encode_url_without_viewport($url) {
    //TODO: Remove url params?
    $ric_options = get_option('ric-setting-group');
    $ric_url = $ric_options['url'];

    return $ric_url . '/' . base64_encode($url);
}

/**
 * @param string $src
 * @return string
 */
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

/**
 * @param array $parameters
 * @return array
 */
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

/**
 * @param integer $id
 * @return false|string
 */
function ric_delete_attachment($id) {

    $post_type = get_post_mime_type($id);
    if(strpos($post_type, "image/") === false) {
        return false;
    }
    /* TODO: We must send something to the server to authenticate the request.
             Otherwise RIC is vulnerable to DoSsing */


    $url =  wp_get_attachment_url( $id );
    if (!ric_already_encoded($url)) {
        $url = ric_encode_url_without_viewport($url);
    }

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded",
            'method'  => 'DELETE'
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    return $result;
}

/**
 * @param string $img
 * @return string
 */
function ric_get_header_image_tag($img) {
    $document = new DOMDocument();
    $document->loadHTML($img, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    $images = $document->getElementsByTagName('img');

    // Iterate each img tag
    foreach ($images as $image) {
        $src = $image->getAttribute('src');
        $new_src = ric_encode_url($src);
        if (@getimagesize($new_src)) {
            $image->setAttribute('src', $new_src);
        }
    }

    return $document->saveHTML();
}

/**
 * @param array $http_response_header
 * @return integer
 */
function getHttpCode($http_response_header = null)
{
    if(is_array($http_response_header))
    {
        $parts=explode(' ',$http_response_header[0]);
        if(count($parts)>1) //HTTP/1.0 <code> <text>
            return intval($parts[1]); //Get code
    }
    return 0;
}

/**
 * @return array
 */
function get_viewport_from_cookie() {
    try {
        $viewport = explode("x", $_COOKIE["RIC_VIEWPORT"], 2);
        if (count($viewport) !== 2) {
            throw new Exception("Bad cookie format for RIC_VIEWPORT");
        }
        $width = intval($viewport[0]);
        $height = intval($viewport[1]);
        if($width === 0 || $height === 0) {
            throw new Exception("Bad width or height for RIC_VIEWPORT");
        }
    } catch(Exception $e) {
        $width = 1920;
        $height = 1080;
    }
    return ["width" => $width, "height" => $height];
}

function ric_detect_viewport() {
    //XXX: No cookies (disable in browser) == infinite reload loop!
    if (empty($_COOKIE["RIC_VIEWPORT"])) {
        // If JS and cookies are enable, each page load will update the viewport
        setcookie("RIC_VIEWPORT", "default", 0, "/");
        echo file_get_contents(dirname(__FILE__).'/viewport-detector.html');
        exit;
    }
}

/**
 * @param string $content
 * @return string
 */
function ric_image_source_manipulator($content) {

    libxml_use_internal_errors(true);
    $post = new DOMDocument();

    $post->loadHTML($content);
    $images = $post->getElementsByTagName('img');

    // Iterate each img tag
    foreach ($images as $image) {
        $src = $image->getAttribute('src');
        if(!filter_var($src, FILTER_VALIDATE_URL) || ric_already_encoded($src)) {
            continue;
        }

        $new_src = ric_encode_url($src);
        if (ric_has_file($new_src)) {
            $image->setAttribute('src', $new_src);
        }
    }

    $divs = $post->getElementsByTagName('div');
    foreach ($divs as $div) {
        $style = $div->getAttribute('style');

        // Search for background-image with regexp
        //
        // background-image : match string
        // \s*              : possible whitespace(s)
        // url\(            : match string url(
        // (["|']?)         : possible ' or "
        // (.+?)            : the url
        // (["|']?)         : possible ' or "
        // \)               : match string )
        $regex = '/(background-image:\s*url\((["|\']?))(.+?)(["|\']?\))/';
        $matches = [];
        if (!empty($style)) {
            preg_match($regex, $style, $matches);
            if (!empty($matches[0])) {
                $src = $matches[3];
                if (!filter_var($src, FILTER_VALIDATE_URL) || ric_already_encoded($src)) {
                    continue;
                }
                $new_src = ric_encode_url($src);
                if (ric_has_file($new_src)) {
                    $replacement = "$1" . ric_encode_url($src) . "$4";
                    $style = preg_replace($regex, $replacement, $style);
                    $div->setAttribute('style', $style);
                }
            }
        }
    }

    return $post->saveHTML();

}


function ric_has_file($url) {
    return true;
    $options = ['http' => ['method'  => 'HEAD']];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    $code = getHttpCode($http_response_header);

    if ($code == 200) {
        return true;
    }

    return false;
}

function ric_ok() {
    $ric_options = get_option('ric-setting-group');
    $ric_url = $ric_options['url'];

//    $options = ['http' => ['method'  => 'GET']];
//
//    $context  = stream_context_create($options);
    $result = @file_get_contents($ric_url . "/status");

    if ($result === "OK" && getHttpCode($http_response_header) == 200) {
        return true;
    }

    return false;
}

if (ric_ok()) {
    if (!is_admin()) {
        $ric_viewport = array(1024, 768); //default viewport
        if (isset($_COOKIE["RIC_VIEWPORT"])) {
            $ric_viewport = $_COOKIE["RIC_VIEWPORT"];
        }
        $size = explode('x', $ric_viewport);
        list($width, $height) = $size;
        add_action('wp_head', 'load_js_file');
        add_filter('init', 'ric_detect_viewport', 1);

        add_filter('wp_calculate_image_srcset', 'disable_srcset');

//    add_filter('the_content', 'ric_src_the_content', 15000000);  // hook into filter and use priority 15000000 to make sure it is run after the srcset and sizes attributes have been added.
//    add_action('the_post', 'ric_src_the_post', 15000000);

//    add_filter('wp_get_attachment_image_src', 'ric_wp_get_attachment_image_src' , 15000000);
//    add_filter('get_header_image_tag', 'ric_get_header_image_tag' , 15000000);

//    Using this will break some images. This is lower level hook and outside it WP changes the image url.
//    add_filter('wp_get_attachment_url', 'ric_wp_get_attachment_url' , 15000000);

        ob_start("ric_image_source_manipulator");


    } else {

        add_filter('delete_attachment', 'ric_delete_attachment', 5);
    }
}

run_ric_wp();
