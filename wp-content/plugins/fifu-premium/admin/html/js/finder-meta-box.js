jQuery(document).ready(function () {
    let text = jQuery("div#finderMetaBox").find('h2').text();
    jQuery("div#finderMetaBox").find('h2.hndle').text('');
    jQuery("div#finderMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-search"></span> ' + text + '</h4>');
    jQuery("div#finderMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#finderMetaBox").find('button.handle-order-lower').remove();
});
