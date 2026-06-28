document.addEventListener('DOMContentLoaded', function () {
    const icon = document.getElementById('fifu-server');

    let fetchCount = 0;
    let checkOptionValueCount = 0;
    const maxExecutions = 5;
    let details = icon ? icon.title : '';
    let box = jQuery("#bar");

    function checkOptionValue() {
        if (checkOptionValueCount >= maxExecutions)
            return;
        checkOptionValueCount++;

        jQuery.ajax({
            url: fifuAsyncVars.ajaxUrl,
            method: 'POST',
            data: {
                action: 'fifu_check_status_server',
                security: fifuAsyncVars.nonce
            },
            success: function (response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    if (response.data.option_value) {
                        box.val((box.val() || '') + 'Test ' + checkOptionValueCount + ':success\n');
                        if (!icon)
                            return;
                        icon.style.color = 'rgb(142, 181, 102)';
                        icon.title = fifuAsyncVars.server_ok + '\n\n' + details;
                    } else {
                        box.val((box.val() || '') + 'Test ' + checkOptionValueCount + ':fail\n');
                        if (!icon)
                            return;
                        icon.style.color = 'rgb(163, 60, 46)';
                        icon.title = fifuAsyncVars.server_nok + '\n\n' + details;
                    }
                } else {
                    // Option not ready yet → mark as pending, don't flip icon to red
                    box.val((box.val() || '') + 'Test ' + checkOptionValueCount + ':pending\n');
                    return;
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                // Set the icon to red in case of error
                box.val((box.val() || '') + 'Test ' + checkOptionValueCount + ':fail\n');
                if (!icon)
                    return;
                icon.style.color = 'rgb(163, 60, 46)';
                icon.title = fifuAsyncVars.server_nok + '\n\n' + details;
            },
            complete: function () {
                // Set the border radius on completion
                if (!icon)
                    return;
                icon.style.borderRadius = '50px';
            }
        });
    }

    function fetchAndCheck() {
        if (fetchCount >= maxExecutions)
            return;
        fetchCount++;

        fetch(fifuAsyncVars.restUrl + 'fifu-premium/v2/test_server_api/', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': fifuAsyncVars.nonce
            },
            body: JSON.stringify({}), // Empty body
        })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Wait for 2 seconds before calling checkOptionValue
                    setTimeout(() => {
                        checkOptionValue();
                        // Increase wait to reduce race likelihood
                        setTimeout(fetchAndCheck, 5000);
                    }, 5000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Still wait 2 seconds and try again even if fetch fails
                    setTimeout(fetchAndCheck, 3500);
                });
    }

    setTimeout(() => {
        fetchAndCheck();
    }, 1000);
});

document.addEventListener('DOMContentLoaded', function () {
    const icon = document.getElementById('fifu-key');
    if (!icon)
        return;

    function fetchAndCheckKey() {
        fetch(fifuAsyncVars.restUrl + 'fifu-premium/v2/test_key_api/', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': fifuAsyncVars.nonce
            },
            body: JSON.stringify({}),
        })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data === 200) {
                        icon.style.color = 'rgb(142, 181, 102)';
                        icon.title = fifuAsyncVars.key_success + '\n\n' + fifuAsyncVars.key_success_details;
                        return;
                    }
                    if (data === 403) {
                        icon.style.color = 'rgb(227, 184, 83)';
                        icon.title = fifuAsyncVars.key_expired + '\n\n' + fifuAsyncVars.key_expired_details;
                        let keySelector = 'a[href="admin.php?page=fifu-license-key"]';
                        jQuery(keySelector).text(fifuAsyncVars.expiredText).css({'padding': '5px', 'color': 'white', 'background-color': 'rgb(168, 69, 56)'});
                        return;
                    }
                    icon.style.color = 'rgb(163, 60, 46)';
                    icon.title = fifuAsyncVars.key_invalid + '\n\n' + fifuAsyncVars.key_invalid_details;
                })
                .catch(error => {
                    // Optional: surface error or set a fallback UI state
                    // console.error(error);
                });
    }

    setTimeout(() => {
        fetchAndCheckKey();
    }, 1000);
});
