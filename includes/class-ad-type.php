<?php
/**
 * YieldScale Ad Type
 *
 * Class containing information about the YieldScale ad type
 *
 */
class YieldScale_Ad_Type extends Advanced_Ads_Ad_Type_Abstract{

    /**
     * ID - internal type of the ad type
     *
     * must be static so set your own ad type ID here
     * use slug like format, only lower case, underscores and hyphens
     *
     */
    public $ID = 'yieldscale';

    /**
     * Set basic attributes
     *
     */
    public function __construct() {
        $this->title = __( 'YieldScale', 'yieldscale' );
        $this->description = __( 'Ads from YieldScale.com.', 'yieldscale' );
        $this->parameters = array(
            'content' => '',
        );
    }

    /**
     * Output for the ad parameters metabox
     *
     * @param obj $ad ad object
     */
    public function render_parameters($ad){
        {
            // JUST A QUICK AND DIRTY HACK
            //  TODO: refactor
            $network = Advanced_Ads_Network_Yieldscale::get_instance();
            ?>
            <script>
                jQuery( document ).ready(function ($) {
                    AdvancedAdsAdmin.AdImporter.setup(new YieldscaleNetwork());
                });
            </script>
            <?php
        }


        //  the network variable will be used in the views
        global $external_ad_unit_id, $use_dashicons, $closeable;

        if ($ad->content){
            try{
                $json = json_decode($ad->content);
                if (isset($json->id)) $external_ad_unit_id = $json->id;
            } catch (Exception $e){}
        }

        $network = Advanced_Ads_Network_Yieldscale::get_instance();
        if ($network->is_account_connected()) {
            $network->print_external_ads_list();

            //  the $ad->id contains the id of the id (post_id)
            //  we need the yieldscale id, which is stored in the content of the ad
            ?><textarea name="advanced_ad[content]" style="display:none;"><?php echo $ad->content; ?></textarea><?php
            ?><input type="text" style="display:none;" id="unit-code" value="<?php echo $external_ad_unit_id; ?>" /><?php
        }
        else{
            $connect_link_label = sprintf(__( 'Connect to %1$s', 'advanced-ads' ), $network->get_display_name());
            ?>
            <a href="<?php echo $network->get_settings_href() ?>" style="padding:0 10px;font-weight:bold;"><?php echo $connect_link_label ?></a>
            <?php
        }
    }

    /**
     * Prepare the ads frontend output
     *
     * @param obj $ad ad object
     * @return str $content ad content prepared for frontend output
     */
    public function prepare_output($ad){
        $content = ( isset( $ad->content ) ) ? $ad->content : '';
        $obj = json_decode($content);
        $body = "";
        if ($obj && isset($obj->body)) {
            $is_amp = advads_is_amp();
            if ($is_amp && isset($obj->ampbody)) {
                $body = $obj->ampbody;
            } else {
                $body = $obj->body;
            }
        }
        ob_start();
        echo $body;
        return ob_get_clean();
    }

}