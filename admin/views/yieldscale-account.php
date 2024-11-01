<?php
$network = Advanced_Ads_Network_Yieldscale::get_instance();
$account_holder = $network->get_account_options()->user_name;
$yieldscale_nonce = $network->get_nonce();
$is_connected = $network->is_account_connected();
?>
<div id="yieldscale-account" <?php if ( $is_connected ) echo 'style="display:none;"' ?>>
    <div class="widget-col">
        <h3><?php _e( 'Yes, I have a YieldScale account', 'advanced-ads' ) ?></h3>
        <a id="yieldscale-connect" class="button-primary <?php echo ! Advanced_Ads_Checks::php_version_minimum() ? 'disabled ' : ''; ?>preventDefault" <?php echo ! Advanced_Ads_Checks::php_version_minimum() ? 'disabled' : ''; ?>><?php _e( 'Connect to YieldScale', 'advanced-ads' ) ?></a>
    </div>
    <div class="widget-col">
        <h3><?php _e( "No, I still don't have a YieldScale account", 'advanced-ads' ) ?></h3>
        <a id="yieldscale-register" class="button button-secondary preventDefault"><?php _e( 'Get a free YieldScale account', 'advanced-ads' ); ?></a>
    </div>
</div>

<div id="yieldscale-account-connected" <?php if ( ! $is_connected ) echo 'style="display:none;"' ?>>
    <div class="widget-col">
        <strong><?php esc_html_e( 'Connected To YieldScale', 'advanced-ads' )?></strong>
        <?php if ($account_holder && strlen($account_holder)):?>
            <p class="description"><?php esc_html_e( 'Account holder name', 'advanced-ads' ); echo ': <strong>' . $account_holder . '</strong>'; ?></p>
        <?php endif;?>
        <p><a id="yieldscale-revoke" class="button-secondary preventDefault"><?php _e( 'Revoke API access', 'advanced-ads' ) ?></a></p>
    </div>
</div>


<script>
(function($){
    const yieldscale_request = function(data, fnProcess){
        const that = this;
        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: data,
            success:function(response, status, XHR){
                if (response.error){
                    that.error(response.error, ' ');
                }
                else{
                    fnProcess(response);
                }
            }
        });
    };

    jQuery('#yieldscale-connect').click(function(){
        const apiKey = yieldscaleAdvancedAdsJS.apiKey;
        yieldscale_request({
            action: 'yieldscale_login',
            nonce: yieldscaleAdvancedAdsJS.nonce,
            key: apiKey
        }, function(response){
            //  when there was no error - we can redirect the user to login or register an account
            window.location = "https://" + response.callback;
        });
    });

    jQuery('#yieldscale-register').click(function(){
        const apiKey = yieldscaleAdvancedAdsJS.apiKey;
        yieldscale_request({
            action: 'yieldscale_register',
            nonce: yieldscaleAdvancedAdsJS.nonce,
            key: apiKey
        }, function(response){
            //  when there was no error - we can redirect the user to login or register an account
            window.location = "https://" + response.callback;
        });
    });

    jQuery('#yieldscale-revoke').click(function(){
        yieldscale_request({
            action: 'advads_yieldscale_revoke_access',
            nonce: yieldscaleAdvancedAdsJS.nonce,
        }, function(response){
            jQuery("div#yieldscale-account-connected").css('display', 'none');
            jQuery("div#yieldscale-account").css('display', 'block');
        });
    });
})(window.jQuery);
</script>