jQuery(document).ready(function () {
    // start
    jQuery('input[name^=fifu_image_url').each(function () {
        let url = jQuery(this).val();
        let i = jQuery(this).attr('name').split('[')[0].split('_')[3];
        let loop = jQuery(this).attr('name').split('[')[1].split(']')[0];
        fifu_woo_var_get_image(url, i, loop);
    });

    // input
    jQuery('input[name^=fifu_image_url').on('input', function (evt) {
        evt.stopImmediatePropagation();
        let url = jQuery(this).val();
        let i = jQuery(this).attr('name').split('[')[0].split('_')[3];
        let loop = jQuery(this).attr('name').split('[')[1].split(']')[0];
        fifu_woo_var_get_image(url, i, loop);
    });
});

function fifu_woo_var_get_image(url, i, loop) {
    var image = new Image();
    jQuery(image).attr('onload', 'fifu_woo_var_store_sizes(this,' + i + ',' + loop + ');');
    jQuery(image).attr('src', url);
}

function fifu_woo_var_store_sizes($, i, loop) {
    let selectorWidth = 'input[name="fifu_var_input_width' + (i != undefined ? '_' + i : '') + '[' + loop + ']"';
    let selectorHeight = 'input[name="fifu_var_input_height' + (i != undefined ? '_' + i : '') + '[' + loop + ']"';
    jQuery(selectorWidth).val($.naturalWidth);
    jQuery(selectorHeight).val($.naturalHeight);
}
