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

function load_js_file()
{
    wp_enqueue_script('image-loader', plugins_url('/image-loader.js', __FILE__));
}

function add_ric_image_src($page_content) {
    if(empty($page_content)) {
        return $page_content;
    }
    //Disable DOM error reporting
    libxml_use_internal_errors(true);

    $ric_options = get_option('ric-setting-group');
    $ric_url = $ric_options['url'];
    $post = new DOMDocument();

    $post->loadHTML($page_content);

    $images = $post->getElementsByTagName('img');

    // Iterate each img tag
    foreach ($images as $image) {
        $src = $image->getAttribute('src');
        $image->removeAttribute('src');

        $width = $image->getAttribute('width');
        $image->removeAttribute('width');

        $height = $image->getAttribute('height');
        $image->removeAttribute('height');

        $filename = basename($src);

        $new_src = $ric_url . '/' . $filename;

        // Only change the image url if it exist on remote server
        if (@getimagesize($new_src)) {
            $image->setAttribute('data-src', $new_src);
            if ($width !== "") {
                $image->setAttribute('data-width', $width);
            }
            if ($height !== "") {
                $image->setAttribute('data-height', $height);
            }
        }
    };

    return $post->saveHTML();
}

add_action('wp_head', 'load_js_file');
add_filter('wp_calculate_image_srcset', 'disable_srcset');
add_filter('the_content', 'add_ric_image_src', 15);  // hook into filter and use priority 15 to make sure it is run after the srcset and sizes attributes have been added.

run_ric_wp();

?>
