<?php
require_once ADVADS_BASE_PATH . "admin/includes/class-ad-network.php";
require_once YIELDSCALE_BASE_PATH . "includes/class-ad-type.php";

class Advanced_Ads_Network_Yieldscale extends Advanced_Ads_Ad_Network{
    const API_KEY = 'WBGc442354ced86c041990ce36fb2b51064';
    const OPTION_NAME_ACCOUNT = 'yieldscale-account';
    const OPTION_NAME_AD_UNITS = 'yieldscale-ad-units';
    const OPTION_NAME_AD_UNITS_GLOBAL = 'GLOBAL';
    private static $instance;
    private $api;
    private $account_options;
    private $unit_options;

    /**
     * retrieves an instance of the ad type for this ad network
     * @return Advanced_Ads_Network_Yieldscale
     */
    public static function get_instance(){
        if (! self::$instance){
            self::$instance = new Advanced_Ads_Network_Yieldscale();
        }
        return self::$instance;
    }

    public function __construct(){
        parent::__construct('yieldscale', 'YieldScale');
        add_filter('advanced-ads-types-without-size', function($vals){
            if (!$vals) $vals = array();
            $vals[] = $this->identifier;
            return $vals;
        });
    }

    public function register()
    {
        parent::register();

        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_action('wp_ajax_yieldscale_login', array($this, 'ajax_login'));
            add_action('wp_ajax_yieldscale_register', array($this, 'ajax_register'));

            add_action('wp_ajax_advads_yieldscale_user_secret', array($this, 'ajax_submit_user_secret'));
            add_action('wp_ajax_advads_yieldscale_revoke_access', array($this, 'ajax_revoke_access'));
            add_action('wp_ajax_advads_yieldscale_get_ad_details', array($this, 'ajax_get_ad_details'));
        }
        //add the page that handles the callbacks for retrieving the user secret
        add_action('admin_menu', function (){
            add_submenu_page(null,
                "Page Title",
                "Menu Title",
                "advanced_ads_manage_options",
                "ys-callback",
                array($this, "yieldscale_callback_url")
            );
        });
    }

    public function yieldscale_callback_url(){
        $this->receive_user_secret();
        //  headers were already sent at this point, so we cannot forward.
        $url = admin_url('admin.php?page=advanced-ads-settings#top#yieldscale');
//        $url = self::get_site_url_formatted() . '/wp-admin/admin.php?page=advanced-ads-settings#top#yieldscale';
        ?>
        <script>
            window.location = '<?php echo $url?>';
        </script>
        <?php
    }

    /**
     * retrieves an instance of the ad type for this ad network
     */
    public function get_ad_type()
    {
        return new YieldScale_Ad_Type();
    }

    /**
     * the ad units and their codes will be allocated and stored inside of these wp options
     */
    public function get_unit_options(){
        if (! $this->unit_options){
            $this->unit_options = get_option(self::OPTION_NAME_AD_UNITS, array());
        }
        return $this->unit_options;
    }

    /**
     * whenever the details of an ad unit are fetched from the YieldScale server a set of ads.txt entries and some
     * header code (to be put in html head) is returned.
     * we need a globally valid source of data for generating the ads.txt file and making the proper adjustments to the html head.
     * that's why we will save and use the data of the most recent ad details. this globally valid data will be stored
     * inside the unit_options under the key self::OPTION_NAME_AD_UNITS_GLOBAL
     */
    public function get_global_unit_options(){
        $unit_options = $this->get_unit_options();
        if (isset($unit_options[self::OPTION_NAME_AD_UNITS_GLOBAL])){
            return $unit_options[self::OPTION_NAME_AD_UNITS_GLOBAL];
        }
        return array();
    }

    /**
     * this method will be called via wp AJAX.
     * it has to retrieve the list of ads from the ad network and store it as an option
     * does not return ad units - use "get_external_ad_units" if you're looking for an array of ad units
     */
    public function update_external_ad_units() {
        $api = $this->get_api();
        $domain = self::get_site_domain_formatted();
        $response = $api->get_all_ad_units($domain);
        if ($response->has_error()){
            if ($response->error_code == -6){
                // no ad units for the given domain
            }
            else {
                $this->send_ajax_error_response_and_die($response->error_text . " (" . $response->error_code . ")");
            }
        }
        else {
            $unit_options = $this->get_unit_options();
            $global_bak = isset($unit_options[self::OPTION_NAME_AD_UNITS_GLOBAL])
                ? $unit_options[self::OPTION_NAME_AD_UNITS_GLOBAL]
                : array();

            $new_options = array(self::OPTION_NAME_AD_UNITS_GLOBAL => $global_bak);
            foreach ($response->ad_units as $unit) {
                $option = array();
                $unit->domain = $domain; //store the domain
                $option['unit'] = $unit;
                $new_options[$unit->id] = $option;
            }
            $this->unit_options = $new_options;
            update_option(self::OPTION_NAME_AD_UNITS, $new_options);
        }

        ob_start();
        $ad_units = $this->get_external_ad_units();
        $network = $this;
        include GADSENSE_BASE_PATH . 'admin/views/external-ads-list.php';
        $ad_selector = ob_get_clean();

        $response = array(
            'status' => true,
            'html'   => $ad_selector,
        );

        $this->send_ajax_response_and_die($response);
    }

    /**
     * adds the custom wp settings to the tab for this ad unit
     */
    protected function register_settings($hook, $section_id)
    {
        // add a setting field to manage the account
        add_settings_field(
            'yieldscale-account',
            __( 'Yieldscale Account', 'advanced-ads' ),
            array($this, 'render_settings_account'),
            $hook,
            $section_id
        );
    }

    /**
     * sanitize the network specific options
     * @param $options the options to sanitize
     * @return mixed the sanitizzed options
     */
    protected function sanitize_settings($options)
    {
        return $options;
    }

    /**
     * sanitize the settings for this ad network
     * @param $ad_settings_post
     * @return mixed the sanitized settings
     */
    public function sanitize_ad_settings($ad_settings_post)
    {
        //  check if we have to get the code for this ad
        //  there is no api call limit, so it might be good to always refresh
        return $ad_settings_post;
    }

    /**
     * @return array of ad units (Advanced_Ads_Ad_Network_Ad_Unit)
     */
    public function get_external_ad_units()
    {
        $ad_units = get_option(self::OPTION_NAME_AD_UNITS);
        $units = array();
        $domain = self::get_site_domain_formatted();

        if (isset($ad_units) && is_array($ad_units) && count($ad_units)){
            //$ad_codes = $mapi_options['ad_codes'];
            foreach ($ad_units as $raw){
                if (! isset($raw['unit'])) continue; //  we might come across the global object, which is ignored
                $rawunit = $raw['unit'];
                if ($rawunit) {
                    $ad_unit = new Advanced_Ads_Ad_Network_Ad_Unit($rawunit);
                    //  check if the domain matches the site domain.
                    if (! isset($rawunit->domain) || $rawunit->domain != $domain)
                        continue;
                    $ad_unit->id = $rawunit->id;
                    $ad_unit->name = isset($rawunit->name) ? $rawunit->name : '-';
                    $ad_unit->active = isset($rawunit->status) && $rawunit->status == "Active";
                    $ad_unit->slot_id = $ad_unit->id; //  this is the id to be displayed in the ads list (slot id of adsense, and not used atm)
                    //  the codes will be fetched seperately on demand. fill in some dummy values for now.
                    $ad_unit->code = "<script></script>";
                    $ad_unit->code_amp = "<script></script>";


                    if (isset($rawunit->backfills)){
                        $display_type = "Hybrid";
                    }
                    else{
                        $display_type = "YieldScale";
                    }
                    $ad_unit->display_type = $display_type;

                    if (isset($rawunit->sizes) && is_array($rawunit->sizes)){
                        $display_size = "";
                        foreach ($rawunit->sizes as $sizes){
                            $sizes = json_decode($sizes);
                            if (is_array($sizes)) {
                                $len = count($sizes);
                                if ($len > 1) {
                                    $display_size .= '<span style="margin:2px; padding: 2px; border:1px dotted #cccccc; border-radius: 3px;">';
                                    for ($i=1; $i<$len; $i++){
                                        $size = $sizes[$i];
                                        if ($i>1) $display_size .= " &nbsp; ";
                                        $display_size .= $size[0] . "x" . $size[1];
                                    }
                                    $display_size .= "</span> ";
                                }
                            }
                        }
                    }
                    else{
                        $display_size = "N/A";
                    }
                    $ad_unit->display_size = $display_size;
                    $units[] = $ad_unit;
                }
            }
        }
        return $units;
    }

    /**
     * checks if the ad_unit is supported by advanced ads.
     * this determines wheter it can be imported or not.
     * @param $ad_unit
     * @return boolean
     */
    public function is_supported($ad_unit)
    {
        // TODO: Implement is_supported() method.
        return true;
    }

    public function is_account_connected()
    {
        $options = $this->get_account_options();
        return $options && isset($options->user_key) && $options->user_key;
    }

    public function get_javascript_base_path()
    {
        return YIELDSCALE_BASE_URL . 'admin/assets/js/yieldscale.js';
    }

    public function append_javascript_data(&$data)
    {
        $data['apiKey'] = self::API_KEY;
        return $data;
    }


    /**
     * @return Yieldscale_API
     */
    public function get_api(){
        if (! $this->api){
            require_once YIELDSCALE_BASE_PATH . 'includes/class-yieldscale-api.php';
            $options = $this->get_account_options();
            $this->api = new Yieldscale_API();
            $this->api->setup(self::API_KEY, $options->user_key);
        }
        return $this->api;
    }

    /**
     * @return Advanced_Ads_Yieldscale_Account_Options
     */
    public function get_account_options(){
        if (! $this->account_options){
            $this->account_options = get_option(self::OPTION_NAME_ACCOUNT, new Advanced_Ads_Yieldscale_Account_Options());
        }
        return $this->account_options;
    }

    public function render_settings_account(){
        require_once YIELDSCALE_BASE_PATH . 'admin/views/yieldscale-account.php';
    }

    public function print_external_ads_list($hide_idle_ads = true){
        //  we need to extract the yieldscale id
        global $external_ad_unit_id, $use_dashicons, $closeable, $ad_units, $network;
        $network = $this;
        $closeable = false;
        $use_dashicons = false;

        $ad_units = $this->get_external_ad_units();
//        include(ADVADS_BASE_PATH . '/modules/gadsense/admin/views/external-ads-links.php');
        include GADSENSE_BASE_PATH . 'admin/views/external-ads-list.php';
        include(YIELDSCALE_BASE_PATH . '/admin/views/yieldscale-ads.php');
    }

    /**
     * this method will be called, when a user submits it's api key for yieldscale
     */
    public function ajax_login($register_instead_of_login = false){
        $this->ajax_security_checks();
        $api = $this->get_api();
        $api->setup(self::API_KEY, null);

//        $callback_url = self::get_site_url_formatted() . "/wp-admin/admin.php?page=ys-callback&nonce=" . $this->get_nonce();
        $callback_url = get_site_url() . "/wp-admin/admin.php?page=ys-callback&nonce=" . $this->get_nonce();
        if ($register_instead_of_login)
            $response = $api->get_registration_link($callback_url);
        else
            $response = $api->get_login_link($callback_url);

        $r = new stdClass();
        $r->callback = $response->link;

        $this->send_ajax_response_and_die($r);
    }

    public function ajax_register(){
        $this->ajax_login(true);
    }

    /**
     * when you connect to your yieldscale account, you will receive a user secret via callback url
     * this method handles this case and retrieves the secret from the request and stores it
     */
    public function receive_user_secret(){
        $this->ajax_security_checks();
        $trimmed_user_secret = trim($_REQUEST['userSecret']);
        $user_secret = esc_attr( $trimmed_user_secret );

        //  try to obtain a user key (will validate the secret on success)
        $api = $this->get_api();
        $api->set_user_secret($user_secret);

        $response = $api->get_user_key();
        if ($response->has_error()){
            //TODO: handle the error
            error_log("There was a problem while trying to retrieve the user secret for YieldScale: " . $response->error_text);
        }
        else {
            //  at this point we know that we are dealing with a valid key / secret.
            //  we won't store the user secret, but the user key that we just received.
            $options = $this->get_account_options();
            $options->user_key = $response->user_key;
            $options->user_name = $response->user_name;
            update_option(self::OPTION_NAME_ACCOUNT, $options);
        }

    }

    /**
     * disconnects the yieldscale account from wordpress
     */
    public function ajax_revoke_access(){
        $this->ajax_security_checks();
        //  just overwrite the options to clear all the traces
        $options = new Advanced_Ads_Yieldscale_Account_Options();
        update_option(self::OPTION_NAME_ACCOUNT, $options);

        $this->send_ajax_response_and_die();
    }

    /**
     * fetches details like head, body, code, amp code
     */
    public function ajax_get_ad_details(){
        $id = isset($_POST['id']) ? absint( $_POST['id'] ) : null;
        if (!$id) $this->send_ajax_error_response_and_die('Missing ID for YieldScale ad.');
        $api = $this->get_api();
        $response = $api->get_code($id);
        $this->handle_error_in_api_response_and_die_on_error($response);

        //  allocate the codes to the matching ad unit and save it
        $data = array(
            "adstxt" => $response->adstxt,
            "head" => $response->head,
            "body" => $response->body,
            "amphead" => $response->amphead,
            "ampbody" => $response->ampbody
        );

        $unit_options = $this->get_unit_options();
        $option = isset($unit_options[$id]) ? $unit_options[$id] : array();
        $option['code'] = $data;
        $unit_options[$id] = $option;

        //  we agreed to always use the latest data for adstxt and head globally, for all yieldscale ads.
        //  so the body is the only thing that differs from unit to unit.
        $unit_options[self::OPTION_NAME_AD_UNITS_GLOBAL] = array(
            "adstxt" => $response->adstxt,
            "head" => $response->head,
            "amphead" => $response->amphead,
        );
        update_option(self::OPTION_NAME_AD_UNITS, $unit_options);

        $this->send_ajax_response_and_die($data);
    }

    /**
     * a little helper method to check an api response for errors, and notify the client.
     * if errors were detected, an error response is sent and the script dies.
     * so it's safe to assume the response was succesful after this method was called.
     * @param $response Yieldscale_Response api response
     */
    private function handle_error_in_api_response_and_die_on_error($response){
        if ($response->has_error()){
            $this->send_ajax_error_response_and_die($response->error_text . " (" . $response->error_code . ")");
        }
    }

//    /**
//     * @Deprecated
//     * this method gets the site url of the blog and removes the www subdomain and any trailing slashes, if present.
//     * @return string the formatted site url
//     */
//    public static function get_site_url_formatted(){
//        preg_match("|^([\d\w]+://)?([^/]+)(.*)|", get_site_url(), $matches);
//        $protocol = $matches[1] ? $matches[1] : "http://";
//        $formatted_domain = preg_replace("|^www\.|", "", $matches[2]);
//        $path = $matches[3];
//        $formatted = $protocol . $formatted_domain . $path;
//        while (substr($formatted, -1) == "/"){
//            $formatted = substr($formatted, 0, strlen($formatted)-1);
//        }
//        return $formatted;
//    }

    /**
     * extracts the domain from the site url and removes the www subdomain (if present)
     * @return string|string[]|null
     */
    public static function get_site_domain_formatted(){
        $domain = Advanced_Ads_Overview_Widgets_Callbacks::get_site_domain();
        return preg_replace("|^www\.|", "", $domain);
        return $formatted;
    }
}

class Advanced_Ads_Yieldscale_Account_Options{
    /**
     * @var string
     */
    public $user_key;
    /**
     * @var string
     */
    public $user_name;
}