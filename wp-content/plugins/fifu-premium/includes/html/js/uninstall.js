jQuery(document).ready(function ($) {
    jQuery('a#deactivate-fifu-premium').click(function (e) {
        e.preventDefault();
        let box = `
            <table>
                <tr>
                    <td><button class="uninstall" style="background-color:#f44336" id="pre-deactivate">${fifuUninstallVars.buttonTextClean}</button></td>
                    <td><button class="uninstall" style="width:100%;background-color:#008CBA"id="deactivate">${fifuUninstallVars.buttonTextDeactivate}</button></td>
                </tr>
                <tr>
                    <td style="color:black;text-align:center">${fifuUninstallVars.buttonDescriptionClean}</td>
                    <td style="color:black;text-align:center">${fifuUninstallVars.buttonDescriptionDeactivate}</td>
                </tr>
            </table>
        `;
        jQuery.fancybox.open(box);
    });

    jQuery(document).on("click", "button#deactivate", function () {
        let href = jQuery('a#deactivate-fifu-premium').attr('href');
        window.location.href = href;
    });

    jQuery(document).on("click", "button#pre-deactivate", function () {
        jQuery('.fancybox-slide').block({message: '', css: {backgroundColor: 'none', border: 'none', color: 'white'}});
        setTimeout(function () {
            jQuery.ajax({
                method: "POST",
                url: fifuUninstallVars.restUrl + 'fifu-premium/v2/pre_deactivate/',
                data: {},
                async: false,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fifuUninstallVars.nonce);
                },
                success: function (data) {
                    let href = jQuery('a#deactivate-fifu-premium').attr('href');
                    window.location.href = href;
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log(jqXHR);
                    console.log(textStatus);
                    console.log(errorThrown);
                },
                complete: function () {
                    // jQuery('.fancybox-slide').unblock();
                }
            });
        }, 250);
    });
});
