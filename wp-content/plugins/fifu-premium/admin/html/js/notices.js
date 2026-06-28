jQuery(document).ready(function ($) {
    jQuery('#fifu-license-expired-notice').on('click', '.notice-dismiss', function () {
        jQuery.ajax({
            url: fifuAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fifu_dismiss_expired_notice',
                nonce: fifuAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    console.log('Notice dismissed successfully.');
                }
            }
        });
    });
});
