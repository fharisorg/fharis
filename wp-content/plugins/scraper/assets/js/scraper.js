var scraper = {
    lastHash: '',
    errorMessage: '',
    init: function () {
        console.log(scraper_service);
    },
    service: function (request, data, callback) {
        jQuery.ajax({
            url: scraper_service.ajax_url,
            type: 'post',
            data: {
                action: 'scraper_service',
                request: request,
                data: data,
                purchase_code: scraper_service.purchase_code
            },
            success: function (response) {
                callback(response);
            },
            error: function (jqXHR) {
                console.log(jqXHR);
                //alert('Request failed, please contact with support team!');
                scraper.errorMessage = jqXHR.responseText;

                jQuery('#loading_icon').hide();

                var outputHTML = '';

                outputHTML += 'Errors enabled, full PHP dump output log with all error and warnings';
                outputHTML += '<pre>' + scraper.errorMessage + '</pre>';
                outputHTML += '<pre>HTTP Code : ' + jqXHR.status + '</pre>';

                jQuery('#scraper_logs').html(outputHTML);
                jQuery('#scraper_logs').show();
                //scraper.showLogs(scraper.lastHash);
            }
        });
    },
    start: function (hash, button) {
        button.disabled = true;
        button.innerText = 'Starting...';

        this.service('start_task', {hash: hash}, function (data) {
            if (data && data.success) {
                window.location.reload();
            } else {
                alert('An error occured!');
            }
        });
    },
    stop: function (hash, button) {
        button.disabled = true;
        button.innerText = 'Stoping...';

        this.service('stop_task', {hash: hash}, function (data) {
            if (data && data.success) {
                window.location.reload();
            } else {
                alert('An error occured!');
            }
        });
    },
    clone: function (hash, button) {
        button.disabled = true;
        button.innerText = 'Cloning...';

        this.service('clone_task', {hash: hash}, function (data) {
            if (data && data.success) {
                window.location.reload();
            } else {
                alert('An error occured!');
            }
        });
    },
    reset: function (hash, button) {
        button.disabled = true;
        button.innerText = 'Resetting...';

        this.service('reset_task', {hash: hash}, function (data) {
            if (data && data.success) {
                window.location.reload();
            } else {
                alert('An error occured!');
            }
        });
    },
    showLogs: function (hash) {
        jQuery('#loading_icon').css('display', 'block');
        jQuery('#output_area').show();

        this.service('get_output_log', {hash: hash}, function (data) {
            if (data && data.success) {
                //window.location.reload();
                jQuery('#loading_icon').hide();
                jQuery('#scraper_logs').show();
                jQuery('#scraper_logs').html(scraper.showOutput(data));

                if (data.insert_error) {
                    jQuery('#scraper_logs').append('<pre>' + data.insert_error + '</pre>');
                }
            } else {
                alert('An error occured!');
            }
        });
    },
    trigger: function (hash, button) {
        //disable all buttons to prevent to lose logs
        scraper.lastHash = hash;
        jQuery('button').attr('disabled', true);
        jQuery('button').removeClass('run-button-clicked');
        jQuery('.change-log-tr').slideUp('slow').remove();
        
        button.disabled = true;
        button.innerText = 'Running...';

        jQuery('#loading_icon').css('display', 'block');
        jQuery('#output_area').show();
        
        //Show loading icon
        button.classList.add("run-button-clicked");
        var clone_loader = jQuery('#loading_icon').clone();
        jQuery('.run-button-clicked').parents('.main-tb-click').parents('tr').after('<tr class="change-log-tr"><td colspan="6"></td></tr>');
        jQuery('.change-log-tr').hide();
        jQuery('.change-log-tr > td').html(clone_loader);
        jQuery('.change-log-tr').slideDown(2000,'swing');
                
        this.service('trigger_task', {hash: hash}, function (data) {
            jQuery('button').attr('disabled', false);
            button.disabled = false;
            button.innerHTML = '<span class="dashicons dashicons-controls-play"></span> Run Now';
            
            if (data && data.success) {
                //window.location.reload();                
                jQuery('#loading_icon').hide();
                jQuery('#output_area').hide();
                jQuery('.change-log-tr > td').html('').html(scraper.showOutput(data));                                                
            } else {
                jQuery('#loading_icon').hide();
                jQuery('#output_area').hide();
                //scraper.showLogs(hash);
                jQuery('.change-log-tr > td').html('').html('Request has been completed but it exceeded timeout or memory limit. Here is the error message from server : ' + scraper.errorMessage);                
            }
        });
    },
    delete: function (hash, button) {
        if (confirm("Are you sure, it will be permanently removed from your task list?")) {
            button.disabled = true;
            button.innerText = 'Deleting...';

            this.service('delete_task', {hash: hash}, function (data) {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    alert('An error occured!');
                }
            });
        }
    },
    showOutput: function (data) {
        var output = [];
        scraper.errorMessage = data;
		if(data.results.length > 0) {
			for (var index in data.results) {
				var item = data.results[index];
				if (item.excluded && item.postTitle) {
					output.push('<li>[' + item.date + '] Post : ' + item.postTitle.substring(0, 20) + '... - <b>Excluded Post</b></li>');
				} else if (!item.is_unique && item.postTitle) {
					output.push('<li>[' + item.date + '] Post : ' + item.postTitle.substring(0, 20) + '... - <b>Already Processed Post</b></li>');
				} else if (item.postTitle) {
					output.push('<li>[' + item.date + '] Post : <a target="_blank" href="../index.php?p=' + item.postId + '&preview=true">' + item.postTitle.substring(0, 20) + '...</a> - ' + (item.success ? '<b>Successfuly Proccessed</b>' : '<b>Error On Process</b>') + '</li>');
				} else if (item.date) {
					output.push('<li>[' + item.date + '] Post ID : <a target="_blank" href="../index.php?p=' + item.postId + '&preview=true">' + item.postId + '</a> - ' + (item.success ? '<b>Successfuly Proccessed</b>' : '<b>Error On Process</b>') + '</li>');
				}
			}
		}
		if(data.request == 'get_output_log') {
			if(data.results.length == 0) {
				return 'There is no post in last log.';
			} else {
				return output;
			}
		}
        if (!data.results['source_connection'] && output.length > 0) {
            return output;
        } else if (data.results.source_connection && data.results.next_page_path_defined) {
            var outputHTML = 'Process has been completed, to jump next page (if there is any), you should trigger post again.';
            return outputHTML;
        } else if (data.success && !data.results) {
            var outputHTML = 'There is no post to process, the task completed loop. (Please check your "Total Run" limit, if there is any)';
            return outputHTML;
        } else {
			var outputHTML = 'There is no post to process!<br><b>Is there any connection to source : </b>' + (data.results.source_connection ? 'Yes' : 'No') + '<br><b>Collected Serial Item URLs : </b>' + (data.results.collected_urls.length);
			outputHTML += '<br><b>HTTP Status Code : </b>' + data.results.http_status_code + '<br>';
			if (!data.results.source_connection) {
				outputHTML += '<br><b>Please check your hosting\'s connection to source site, it looks like there is no successful connection to source website.</b>';
			}
			if (data.results.source_connection && data.results.collected_urls.length == 0) {
				outputHTML += '<br><b>Please check your serial xpath, it looks like source doesn\'t provide feed items.</b>';
			}
			if (data.results.collected_urls.length <= data.results.last_index) {
				outputHTML += '<br><b>It passed last index, there is no more post on that page. Reset this task to scan same page again.</b>';
			}
            return outputHTML;
        }
    },
	export: function (hash, button) {
		//disable all buttons to prevent to lose logs
		scraper.lastHash = hash;
		button.disabled = true;
		jQuery('button').attr('disabled', true);
		jQuery('button').removeClass('run-button-clicked');
		jQuery('.change-log-tr').slideUp('slow').remove();
		jQuery('#loading_icon').css('display', 'block');
		jQuery('#output_area').show();

		//Show loading icon
		button.classList.add("run-button-clicked");
		var clone_loader = jQuery('#loading_icon').clone();
		jQuery('.run-button-clicked').parents('.main-tb-click').parents('tr').after('<tr class="change-log-tr"><td colspan="6"></td></tr>');
		jQuery('.change-log-tr').hide();
		jQuery('.change-log-tr > td').html(clone_loader);
		jQuery('.change-log-tr').slideDown(2000, 'swing');

		jQuery.ajax({
			url: scraper_service.ajax_url,
			type: 'post',
			data: {
				action: 'scraper_export_service',
				purchase_code: scraper_service.purchase_code,
				hash: hash,
			},
			success: function (data) {},
			error: function (jqXHR) {
				scraper.errorMessage = jqXHR.responseText;
				jQuery('#loading_icon').hide();
				var outputHTML = '';
				outputHTML += 'Errors enabled, full PHP dump output log with all error and warnings';
				outputHTML += '<pre>' + scraper.errorMessage + '</pre>';
				outputHTML += '<pre>HTTP Code : ' + jqXHR.status + '</pre>';
				jQuery('#scraper_logs').html(outputHTML);
				jQuery('#scraper_logs').show();
			}
		}).done(function (data, textStatus, request) {
			jQuery('button').attr('disabled', false);
			button.disabled = false;
			jQuery('#loading_icon').hide();
			jQuery('#output_area').hide();
			jQuery('.change-log-tr > td').html('');
			var fileName = 'export.csv';
			var disposition = request.getResponseHeader('Content-Disposition');
			if (disposition && disposition.indexOf('filename') !== -1) {
				var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
				var matches = filenameRegex.exec(disposition);
				if (matches != null && matches[1]) {
					fileName = matches[1].replace(/['"]/g, '');
				}
			}
			var contentTypeHeader = request.getResponseHeader("Content-Type");
			var blob = new Blob([data], {type: contentTypeHeader});
			if (window.navigator && window.navigator.msSaveOrOpenBlob) {
				window.navigator.msSaveOrOpenBlob(blob, fileName);
			} else {
				var downloadLink = window.document.createElement('a');
				downloadLink.href = window.URL.createObjectURL(blob);
				downloadLink.download = fileName;
				document.body.appendChild(downloadLink);
				downloadLink.click();
				document.body.removeChild(downloadLink);
				window.URL.revokeObjectURL(downloadLink.href);
			}
		});
		return false;
	},
};

function download_file(url, data, callback) {
	var $iframe,
			iframe_doc,
			iframe_html;

	if (($iframe = jQuery('#download_iframe')).length === 0) {
		$iframe = jQuery("<iframe id='download_iframe' style='display: none' src='about:blank'></iframe>").appendTo("body");
	}
	iframe_doc = $iframe[0].contentWindow || $iframe[0].contentDocument;
	if (iframe_doc.document) {
		iframe_doc = iframe_doc.document;
	}
	iframe_html = "<html><head></head><body><form method='POST' action='" + url + "'>"
	Object.keys(data).forEach(function (key) {
		iframe_html += "<input type='hidden' name='" + key + "' value='" + data[key] + "'>";
	});
	iframe_html += "</form></body></html>";
	iframe_doc.open();
	iframe_doc.write(iframe_html);
	jQuery(iframe_doc).find('form').submit();
	callback();
}

window.addEventListener("message", function (event) {
    console.log(event.data);

    var data = event.data;

    if (data.key && data.from == 'editor') {
        if (data.key == 'redirection') {
            window.location.search = '?page=scraper_tasks';
        } else {
            scraper.service(data.key, {}, function (data) {
                //document.getElementById('scraper-visual-editor').contentWindow.pushSiteService(data);
                document.getElementById('scraper-visual-editor').contentWindow.postMessage({key: 'pushSiteService', value: data, from: 'plugin'}, '*');
            });
        }
    }
});

jQuery(document).ready(function () {
    jQuery('.view-switch .scraper-view-button').click(function (event) {
        event.preventDefault();
        if(jQuery(this).hasClass('current')){
            return false;
        }
        jQuery('.scraper-view-button').removeClass('current');
        var $this = jQuery(this),
            view = $this.text();
        jQuery('body').removeClass('scraper-Simple-View scraper-Expanded-View');
        jQuery('body').addClass('scraper-' + view.replace(/\s+/g, '-') );
        $this.addClass('current');
        jQuery.ajax({
            url: scraper_service.ajax_url,
            type: 'post',
            data: {
                action: 'scraper_view_update',
                view: view,
            },
            success: function (response) {
                if(response == 'success'){                    
                    //Nothing to do
                }
            },
            error: function (jqXHR) {
                console.log(jqXHR);
            }
        });
        return false;
    });
    jQuery(document).on('click', '.toggle-cron-url', function (event) {
        event.preventDefault();
        jQuery(this).parents('td').find('.scraper-cron-url-box').toggle('slow');
        return false;
    });
});