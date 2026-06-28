jQuery(document).ready(function () {
    let text = jQuery("div#popupMetaBox").find('h2').text();
    jQuery("div#popupMetaBox").find('h2.hndle').text('');
    jQuery("div#popupMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-fullscreen-alt"></span> ' + text + '</h4>');
    jQuery("div#popupMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#popupMetaBox").find('button.handle-order-lower').remove();
});
