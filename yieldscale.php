<?php
/*
 * Plugin Name:       YieldScale
 * Plugin URI:        https://yieldscale.com
 * Description:       Display ads from YieldScale.com on your WordPress blog
 * Version:           1.3
 * Author:            yieldscale
 * Author URI:        https://wpadvancedads.com
 * Text Domain:       yieldscale
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * based on the Advanced Ads plugin
*/

if (!defined('ABSPATH')) {
    die('-1');
}
if(! in_array('advanced-ads/advanced-ads.php', apply_filters('active_plugins', get_option('active_plugins')))){
    //  raise a notice and provide a download / activation link for advanced ads
    add_action('admin_notices', function(){
        $plugin_data = get_plugin_data(__FILE__);
        $plugins = get_plugins();
        if( isset( $plugins['advanced-ads/advanced-ads.php'] ) ){ // is installed, but not active
            $link = '<a class="button button-primary" href="' . wp_nonce_url( 'plugins.php?action=activate&amp;plugin=advanced-ads/advanced-ads.php&amp', 'activate-plugin_advanced-ads/advanced-ads.php' ) . '">'. __('Activate Now', 'ads-for-visual-composer') .'</a>';
        } else {
            $link = '<a class="button button-primary" href="' . wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=' . 'advanced-ads'), 'install-plugin_' . 'advanced-ads') . '">'. __('Install Now', 'ads-for-visual-composer') .'</a>';
        }
        echo '
        <div class="error">
          <p>'.sprintf(__('<strong>%s</strong> requires the <strong><a href="https://wpadvancedads.com" target="_blank">Advanced Ads</a></strong> plugin to be installed and activated on your site.', 'yieldscale'), $plugin_data['Name']) .
            '&nbsp;' . $link . '</p></div>';
    });
    return;
}
define( 'YIELDSCALE_ADVADS_MIN_VERSION', '1.14');
if (defined('ADVADS_VERSION') && version_compare(ADVADS_VERSION, YIELDSCALE_ADVADS_MIN_VERSION, '<')){
    //  raise a notice and provide a download / activation link for advanced ads
    add_action('admin_notices', function(){
        echo '
            <div class="error">
              <p>'.sprintf(__('<strong>%s</strong> requires <strong><a href="https://wpadvancedads.com" target="_blank">Advanced Ads</a></strong> version %s or higher.', 'yieldscale'), 'YieldScale', YIELDSCALE_ADVADS_MIN_VERSION) .
            '</p></div>';
    });
    return;
}

if( ! class_exists('YieldScale') && version_compare(PHP_VERSION, '5.6.0') === 1 ) {

    // load basic path to the plugin
    define( 'YIELDSCALE_BASE_PATH', plugin_dir_path( __FILE__ ) );
    define( 'YIELDSCALE_BASE_URL', plugin_dir_url( __FILE__ ) );
    define( 'YIELDSCALE_BASE_DIR', dirname( plugin_basename( __FILE__ ) ) ); // directory of the plugin without any paths
    define( 'YIELDSCALE_VERSION', '0.1' );
    
    add_action( 'plugins_loaded', 'yieldscale_load' );
}

function yieldscale_load(){
    require_once YIELDSCALE_BASE_PATH . 'includes/class-network-yieldscale.php';
    $network = Advanced_Ads_Network_Yieldscale::get_instance();
    $network->register();

    if ($network->is_account_connected()) {
        add_action('wp_head', 'yieldscale_inject_script');
        add_filter('advanced-ads-ads-txt-content', 'yieldscale_ads_txt');
    }
}

function yieldscale_inject_script(){
    $is_amp = advads_is_amp();
    $network = Advanced_Ads_Network_Yieldscale::get_instance();
    $opts = $network->get_global_unit_options();
    $head = "<script async=true src='https://cdns.yieldscale.com/ysmin.js'></script>";
    if ($is_amp && isset($opts["amphead"])){
        $head = $opts["amphead"];
    }
    else if (isset($opts["head"])){
        $head = $opts["head"];
    }
    echo $head;
}

function yieldscale_ads_txt($content){
    $network = Advanced_Ads_Network_Yieldscale::get_instance();
    $opts = $network->get_global_unit_options();
    if ($opts && isset($opts['adstxt'])) {
        $content .= $opts['adstxt'] . "\n";
    }
    return $content;
}