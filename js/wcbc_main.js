jQuery(function($){

	$('.bc-editable-link').click(function() {
		jQuery(this).addClass('hidden').siblings('.bc-editable-input').removeClass('hidden');
		return false;
	});

	$('.bc-editable-cancel').click(function() {
		jQuery(this).parent('.bc-editable-input').addClass('hidden').siblings('.bc-editable-link').removeClass('hidden');
		return false;
	});

	$('.bc-editable-select2').select2({
  		ajax: {
			url: ajaxurl, // AJAX URL is predefined in WordPress admin
			dataType: 'json',
			delay: 250, // delay in ms while typing when to perform a AJAX search
			data: function (params) {
  				return {
    				q: params.term, // search query
    				action: 'wcbc_translasi_item' // AJAX action for admin-ajax.php
  				};
			},
			error: function (jqXHR, status, error) {
	            console.log(error + ": " + jqXHR.responseText);
	            return { results: [] }; // Return dataset to load after error
	        },
			processResults: function( data ) {
				return {
					results: data
				};
			},
			cache: false
		},
		minimumInputLength: 3, // the minimum of symbols to input before perform a search
		placeholder: 'Pilih Item Beecloud',
		allowClear: true,
	});

	$('.bc-editable-submit').click(function() {
		let thisBtn = $(this);
		let wc_item_id = $(this).siblings('.bc-editable-wc_item_id').val();
		let bc_item_id = $(this).siblings('.bc-editable-select2').find(':selected').val();
		let bc_item_code = $(this).siblings('.bc-editable-select2').find(':selected').text();

		if(bc_item_id) {

			$.post(ajaxurl, 
				{
    				action: 'wcbc_check_used_item',
    				bc_item_id: bc_item_id, 
  				},
  				function(resp) {
  					let conf = true;
  					let data = JSON.parse(resp);
  					if(data.status) {
  						let error = "Item ini sudah pernah di translasikan:\n";
  						for (index = 0; index < (data.data).length; ++index) {
  							error += "- "+data.data[index]+"\n";
						}
						error += "\nAnda yakin akan tetap melanjutkan?";
  						conf = confirm(error);
  					}

  					if(conf) {
						$.post(ajaxurl, 
							{
			    				action: 'wcbc_translasi_item_save',
			    				wc_item_id: wc_item_id, 
			    				bc_item_id: bc_item_id, 
			    				bc_item_code: bc_item_code, 
			  				},
			  				function(resp) {
			  					if(resp) {
			  						thisBtn.parents('.bc-editable-input').siblings('.bc-editable-link').text(resp);
			  						thisBtn.siblings('.bc-editable-cancel').trigger('click');
			  						thisBtn.parent().siblings('.bc-editable-success').fadeIn(100).fadeOut(1250);
			  					}
			  					else {
			  						alert('Error!');
			  					}
			  				}
						);
  					}
  				}
			);
		}
		else {
			alert('Item Beecloud harus diisi');
		}
		return false;
	});


	let s2_wh = $('#bc-wh-select2').select2({
  		ajax: {
			url: ajaxurl, // AJAX URL is predefined in WordPress admin
			dataType: 'json',
			delay: 250, // delay in ms while typing when to perform a AJAX search
			data: function (params) {
  				return {
    				q: params.term, // search query
    				action: 'wcbc_get_wh' // AJAX action for admin-ajax.php
  				};
			},
			error: function (jqXHR, status, error) {
	            console.log(error + ": " + jqXHR.responseText);
	            return { results: [] }; // Return dataset to load after error
	        },
			processResults: function( data ) {
				return {
					results: data
				};
			},
			cache: true
		},
		minimumInputLength: 1, // the minimum of symbols to input before perform a search
		placeholder: 'Semua',
		allowClear: true,
		// tags: true,
	});

	$('#bc-wh-select2').on('select2:select', function(s2) {
		let data = s2.params.data;
		let hidden = "<input type='hidden' name='bc-wh-value["+data.id+"]' value='"+data.text+"'/>";
		$(this).after(hidden);
	});

	$('#bc-wh-select2').on('select2:unselect', function(s2) {
		let data = s2.params.data;
		let actualData = $('#bc-wh-select2').select2('data');
		let remove = true;
		for(let i = 0; i < actualData.length; i++) {
			if(actualData[i].text == data.text) {
				remove = false;
				break;
			}
		}
		if(remove) {
			$('[name="bc-wh-value['+data.id+']"]').remove();
		}
	});

	$('#bc-pricelvl-select2').select2({
  		ajax: {
			url: ajaxurl, // AJAX URL is predefined in WordPress admin
			dataType: 'json',
			delay: 250, // delay in ms while typing when to perform a AJAX search
			data: function (params) {
  				return {
    				q: params.term, // search query
    				action: 'wcbc_get_pricelvl' // AJAX action for admin-ajax.php
  				};
			},
			error: function (jqXHR, status, error) {
	            console.log(error + ": " + jqXHR.responseText);
	            return { results: [] }; // Return dataset to load after error
	        },
			processResults: function( data ) {
				return {
					results: data
				};
			},
			cache: true
		},
		minimumInputLength: 1, // the minimum of symbols to input before perform a search
		placeholder: '',
		allowClear: true,
		// tags: true
	});

	$('#bc-pricelvl-select2').on('change.select2', function(s2) {
		let data = $("#bc-pricelvl-select2 option:selected").text();;
		$('[name="bc-pricelvl-value"]').val(data);
	});

	$('#bc-branch-select2').select2({
  		ajax: {
			url: ajaxurl, // AJAX URL is predefined in WordPress admin
			dataType: 'json',
			delay: 250, // delay in ms while typing when to perform a AJAX search
			data: function (params) {
  				return {
    				q: params.term, // search query
    				action: 'wcbc_get_branch' // AJAX action for admin-ajax.php
  				};
			},
			error: function (jqXHR, status, error) {
	            console.log(error + ": " + jqXHR.responseText);
	            return { results: [] }; // Return dataset to load after error
	        },
			processResults: function( data ) {
				return {
					results: data
				};
			},
			cache: true
		},
		minimumInputLength: 1, // the minimum of symbols to input before perform a search
		placeholder: '',
		allowClear: true,
		// tags: true
	});

	$('#bc-branch-select2').on('change.select2', function(s2) {
		let data = $("#bc-branch-select2 option:selected").text();;
		$('[name="bc-branch-value"]').val(data);
	});

	$('.column-action_process_sync .button').click(function() {
		let thisBtn = $(this);
		let id = thisBtn.attr('data-so');
		let actionsAllowed = ['wcbc_upload_order', 'wcbc_close_order'];
		let action = thisBtn.attr('ajax-action');
		action = actionsAllowed.includes(action) ? action : 'wcbc_upload_order';
		if(!thisBtn.attr('disabled')) {
			thisBtn.attr('disabled', true).find('span.dashicons').removeClass('dashicons-upload').addClass('dashicons-backup');
			$.post(ajaxurl, 
				{
					action: action,
					so_id: id,
				},
				function(resp) {
					if(resp.status) {
						// thisBtn.siblings('.bc-upload-success').fadeIn(100).fadeOut(1250);
						thisBtn.closest('tr').find('.column-sync_status .bc-label').removeClass('danger').removeClass('default').addClass('success').text('Berhasil Tersinkron');
						thisBtn.closest('.column-action_process_sync').siblings('.column-sync_at').text(resp.process_at);
						thisBtn.closest('.column-action_process_sync').siblings('.column-sync_process_at').text(resp.process_at);
						thisBtn.closest('.column-action_process_sync').siblings('.column-sync_note').text('');
						thisBtn.remove();
					}
					else {
						thisBtn.closest('tr').find('.column-sync_status .bc-label').removeClass('danger').removeClass('default').addClass('danger').text('Gagal Tersinkron').fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100);
						thisBtn.closest('.column-action_process_sync').siblings('.column-sync_note').text(resp.data).fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100);
						thisBtn.closest('.column-action_process_sync').siblings('.column-sync_process_at').text(resp.process_at);
					}
					thisBtn.attr('disabled', false).find('span.dashicons').removeClass('dashicons-backup').addClass('dashicons-upload');
				},
				'json'
			);
		}
		return false;
	});

	$('#bc-reload-expired').click(function() {
		let thisBtn = $(this);
		if(!thisBtn.attr('disabled')) {
			thisBtn.attr('disabled', true).val('Loading..');
			$.post(ajaxurl, 
				{
					action: 'wcbc_check_license'
				},
				function(resp) {
					if(resp) {
						document.location.reload();
					}
					else {
						thisBtn.attr('disabled', false).val('Refresh');
					}
				},
				'json'
			);
		}
		return false;
	});

	$('#bc-itemongkir-select2').select2({
  		ajax: {
			url: ajaxurl, // AJAX URL is predefined in WordPress admin
			dataType: 'json',
			delay: 250, // delay in ms while typing when to perform a AJAX search
			data: function (params) {
  				return {
    				q: params.term, // search query
    				action: 'wcbc_get_itemserv' // AJAX action for admin-ajax.php
  				};
			},
			error: function (jqXHR, status, error) {
	            console.log(error + ": " + jqXHR.responseText);
	            return { results: [] }; // Return dataset to load after error
	        },
			processResults: function( data ) {
				return {
					results: data
				};
			},
			cache: true
		},
		minimumInputLength: 1, // the minimum of symbols to input before perform a search
		placeholder: '',
		allowClear: true,
		// tags: true
	});

	$('#bc-itemongkir-select2').on('change.select2', function(s2) {
		let data = $("#bc-itemongkir-select2 option:selected").text();;
		$('[name="bc-itemongkir-value"]').val(data);
	});

	$('#bc-proses-bulk').click(function(e) {
		e.preventDefault(e);
		let btn = $(this);
		if(!btn.hasClass('disabled')) {
			let divLogSuccess = $('#bc-log-bulk-translasi');
			let bulkBy = $('#bc-dropdown-key-bulk-trans').find(':selected').val();
			let isReplace = $('[name="bc-checkbox-replace-bulk"]:checked').val();
			let limIteration = isReplace == 'true' ? $('#bc-replace-process-iteration').val() : $('#bc-non-replace-process-iteration').val();
			let i = 1;
			let success = 0;
			let failed = 0;

			divLogSuccess.html('').hide();
			function bcPostBulkTranslasi(lastid) {

				if (i <= limIteration) {
					btn.html('<i class="dashicons dashicons-update spin" style="margin-top:3px"></i> Sedang diproses ('+i+' dari '+limIteration+')').addClass('disabled');

					$.ajax({
	                    dataType: 'json',
	                    method: 'POST',
	                    url: ajaxurl,
	                    cache: false,
	                    data: {
							action: 'wcbc_bulk_translasi_item',
							bulkPoint: bulkBy,
							replace: isReplace,
							iteration: i,
							lastid: lastid
						}
	                }).done(function(resp) {
	                	++i;
						console.log('duar');

						success += (resp.success != null ? resp.success : 0);
						failed += (resp.fail != null ? resp.fail : 0);

						let logSuccess = '';

						$.each(resp.data, function(i, data) {
							logSuccess += '<tr><td>'+data.ID+'</td><td>';
							if(data.success) {
								logSuccess += '<i class="dashicons dashicons-yes" align="center" style="color:green"></i>';
							}
							else {
								logSuccess += '<i class="dashicons dashicons-no-alt" align="center" style="color:red"></i>';
							}
							logSuccess += '</td></tr>';
						});

						divLogSuccess.append(logSuccess);


	                	return bcPostBulkTranslasi(resp.lastid);
	                });
				}
				else {
					alert('Berhasil Bulk Translasi');

					let logSuccess = '';
					logSuccess += 'Berhasil ditranslasi : '+success;
					logSuccess += '<br>';
					logSuccess += 'Gagal ditranslasi : '+failed;
					logSuccess += '<table class="wp-list-table widefat fixed striped"><thead></tr><th>SKU / Nama</th><th>Berhasil ditranslasi</th></tr></thead></tbody>';
					logSuccess += divLogSuccess.html();
					logSuccess += '</tbody></table>';

					divLogSuccess.html(logSuccess).show();

					btn.html('Proses').removeClass('disabled');
					console.log('selesai semua');
				}
			}
			bcPostBulkTranslasi(0);
		}
	});
});