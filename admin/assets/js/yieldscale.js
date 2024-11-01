class YieldscaleNetwork extends AdvancedAdsAdNetwork{
    constructor() {
        super('yieldscale');
        this.name = 'YieldScale';
    }

    onSelected(){
        jQuery('#tracking-ads-box,#advads-tracking-pitch').hide();
    }

    onBlur(){
		jQuery('#tracking-ads-box,#advads-tracking-pitch').show();
    }

    openSelector(){

    }

    getSelectedId(){
        const slotId = jQuery( '#unit-code' ).val().trim();
        return slotId;
    }

    selectAdFromList(slotId){
        const json = {
            network: this.id,
            id: slotId
        };
		jQuery( '#unit-code' ).val(slotId);
        // AdvancedAdsAdmin.AdImporter.closeAdSelector();
        const code = this.getAdDetails(slotId, json);
        AdvancedAdsAdmin.AdImporter.openExternalAdsList(slotId);
    }
    getCustomInputs() {
        return jQuery('#hope_this_is_not_present');
    }

    getRefreshAdsParameters() {
        return {
            nonce: this.vars.nonce,
            action: 'advanced_ads_get_ad_units_' + this.id
        };
    }

    onDomReady() {
    }

    getAdDetails(slotId, json){
		jQuery( '#mapi-loading-overlay' ).css( 'display', 'block' );
		jQuery.ajax({
            type: 'post',
            url: ajaxurl,
            data: {
                nonce: yieldscaleAdvancedAdsJS.nonce,
                id: slotId,
                action: 'advads_yieldscale_get_ad_details'
            },
            success: function(response,status,XHR){
                if (response.error){
                    AdvancedAdsAdmin.AdImporter.setRemoteErrorMessage(response.error);
                }
                else {
                    for (let i in response) {
                        json[i] = response[i];
                    }
                    jQuery('#advanced-ads-ad-parameters textarea[name="advanced_ad[content]"]').val(JSON.stringify(json));
                }
				jQuery( '#mapi-loading-overlay' ).css( 'display', 'none' );
            },
            error: function(request,status,err){
				jQuery( '#mapi-loading-overlay' ).css( 'display', 'none' );
            },
        });
    }
}