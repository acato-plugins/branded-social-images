function hex_to_rgba(hex) {
	var c;
	// #ABC or #ABCD
	if (/^#([A-Fa-f0-9]{3,4})$/.test(hex)) {
		c = (hex + 'F').substring(1).split('');
		return hex_to_rgba(c[0], c[0], c[1], c[1], c[2], c[2], c[3], c[3]);
	}

	c = '0x' + (hex.substring(1) + 'FF').substring(0, 8);
	return 'rgba(' + [(c >> 24) & 255, (c >> 16) & 255, (c >> 8) & 255, Math.round((c & 255) / 25.5) / 10].join(',') + ')';
}

;(function ($, s) {
	var editor = $('#' + s);
	if (editor.length < 1) {
		return false;
	}

	var imageeditor = editor.find('.area--background .background');
	var logoeditor = editor.find('.area--logo .logo');

	;(function ($) {
		$.fn.attachMediaUpload = function () {
			var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

			// Restore the main ID when the add media button is pressed
			jQuery('a.add_media').on('click', function () {
				console.log('is this really needed?');
				wp.media.model.settings.post.id = wp_media_post_id;
			});

			return $(this).each(function () {
				var file_frame;
				var wrap = $(this),
					input = wrap.find('input').not('.button'),
					preview = wrap.find('.image-preview-wrapper img'),
					current_image_id = input.val(),
					button = wrap.find('.button').not('.remove'),
					remove = wrap.find('.button.remove');

				remove.on('click', function (event) {
					var attachment = {
						id: '0',
						url: 'data:image/png;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='
					};
					preview.attr('src', attachment.url);
					input.trigger('image:select', [attachment]);
					input.val(attachment.id);
					current_image_id = attachment.id;
				});

				// Uploading files
				button.on('click', function (event) {
					event.preventDefault();
					// If the media frame already exists, reopen it.
					if (!file_frame) {
						console.log('new file_frame');
						wp.media.model.settings.post.id = current_image_id;
						// Create the media frame.
						file_frame = wp.media.frames.file_frame = wp.media({
							title: 'Select an image or upload one.',
							button: {
								text: 'Use this image',
							},
							multiple: false // Set to true to allow multiple files to be selected
						});
						console.log(file_frame);
						// When an image is selected, run a callback.
						file_frame.on('select', function () {
							// We set multiple to false so only get one image from the uploader
							attachment = file_frame.state().get('selection').first().toJSON();
							if ('sizes' in attachment && 'og-image' in attachment.sizes) {
								attachment.url = attachment.sizes['og-image'].url;
							}
							// Do something with attachment.id and/or attachment.url here
							preview.attr('src', attachment.url);
							input.trigger('image:select', [attachment]);
							input.val(attachment.id);
							current_image_id = attachment.id;
							// Restore the main post ID
							wp.media.model.settings.post.id = wp_media_post_id;
						}).on('open', function () {
							var selection = file_frame.state().get('selection');
							if (current_image_id) {
								selection.add(wp.media.attachment(current_image_id));
							}
						});
					}
					// Set the post ID to what we want
					// file_frame.uploader.options.uploader.params.post_id = current_image_id;
					// Open frame
					console.log('opening file_frame');
					file_frame.open();
				});
			});
		};
		$.fn.attachFileUpload = function () {
			var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

			// Restore the main ID when the add media button is pressed
			jQuery('a.add_media').on('click', function () {
				console.log('is this really needed?');
				wp.media.model.settings.post.id = wp_media_post_id;
			});

			return $(this).each(function () {
				var file_frame;
				var wrap = $(this),
					input = wrap.find('input').not('.button'),
					current_image_id = input.val(),
					button = wrap.find('.button').not('.remove');

				// Uploading files
				button.on('click', function (event) {
					event.preventDefault();
					// If the media frame already exists, reopen it.
					if (!file_frame) {
						console.log('new file_frame');
						wp.media.model.settings.post.id = current_image_id;
						// Create the media frame.
						file_frame = wp.media.frames.file_frame = wp.media({
							title: 'Select a file or upload one',
							button: {
								text: 'Use this file',
							},
							library: {
								type: wrap.data('types').split(',')
							},
							multiple: false // Set to true to allow multiple files to be selected
						});
						console.log(file_frame);
						// When an image is selected, run a callback.
						file_frame.on('select', function () {
							// We set multiple to false so only get one image from the uploader
							attachment = file_frame.state().get('selection').first().toJSON();
							// Do something with attachment.id and/or attachment.url here
							input.trigger('file:select', [attachment]);
							console.log(attachment);
							input.val(attachment.id);
							current_image_id = attachment.id;
							// Restore the main post ID
							wp.media.model.settings.post.id = wp_media_post_id;
						}).on('open', function () {
							var selection = file_frame.state().get('selection');
							if (current_image_id) {
								selection.add(wp.media.attachment(current_image_id));
							}
						});
					}
					// Set the post ID to what we want
					// file_frame.uploader.options.uploader.params.post_id = current_image_id;
					// Open frame
					console.log('opening file_frame');
					file_frame.open();
				});
			});
		};
	})(jQuery);

	$(document).ready(function () {
		$('div[contenteditable]').keydown(function (e) {
			// trap the return key being pressed
			if (e.keyCode === 13) {
				// insert 2 br tags (if only one br tag is inserted the cursor won't go to the next line)
				document.execCommand('insertHTML', false, '<br/>');
				// prevent the default behaviour of return key pressed
				return false;
			}
		});

		// bind editable
		var texteditor = editor.find('.editable');
		var texteditor_target = editor.find('textarea.editable-target');

		// on load set text to editable
		texteditor.html(texteditor_target.val());

		// update target when edited
		texteditor.on('blur keyup paste input', function () {
			texteditor_target.val(texteditor.html().replace(/&nbsp;<br/g, '<br'));
		});

		// update editor when target edited
		texteditor_target.on('blur keyup paste', function () {
			texteditor.html(texteditor_target.val().replace(/\n/g, '<br />'));
		});

		// text color
		editor.find('#color').on('keyup blur paste input', function () {
			editor.get(0).style.setProperty('--text-color', hex_to_rgba($(this).val()));
		});
		// text background color
		editor.find('#background_color').on('keyup blur paste input', function () {
			editor.get(0).style.setProperty('--text-background', hex_to_rgba($(this).val()));
		}).trigger('blur');
		// text shadow options
		editor.find('#text_shadow_color').on('keyup blur paste input', function () {
			editor.get(0).style.setProperty('--text-shadow-color', hex_to_rgba($(this).val()));
		}).trigger('blur');
		editor.find('#text_shadow_top').on('keyup blur paste input', function () {
			editor.get(0).style.setProperty('--text-shadow-top', parseInt($(this).val(), 10) + 'px');
		}).trigger('blur');
		editor.find('#text_shadow_left').on('keyup blur paste input', function () {
			editor.get(0).style.setProperty('--text-shadow-left', parseInt($(this).val(), 10) + 'px');
		}).trigger('blur');
		editor.find('#text_shadow_enabled').on('change', function () {
			if ($(this).is(':checked')) {
				editor.get(0).style.setProperty('--text-shadow-color', hex_to_rgba('#555555DD'));
			} else {
				editor.get(0).style.setProperty('--text-shadow-color', hex_to_rgba('#00000000'));
			}
		}).trigger('change');

		// positions
		$('.wrap-position-grid input').on('change', function () {
			var c = $(this).closest(".wrap-position-grid");
			var n = c.data('name');
			editor.removeClass(function (index, className) {
				return (className.match(new RegExp('(^|\\s)' + n + '-\\S+', 'g')) || []).join(' ');
			}).addClass(n + '-' + c.find('input:checked').attr('value'));
		}).trigger('change');

		// logo size
		$('#image_logo_size').on('keyup blur paste input', function () {
			var v = parseInt("0" + $(this).val(), 10);
			if (v < 5) {
				v = 5;
			}
			if (v > 95) {
				v = 95;
			}
			editor.get(0).style.setProperty('--logo-scale', v);

		}).trigger('blur');

		// sliders
		$('.add-slider').each(function () {
			var $input = $(this).find('input');
			$input.attr('size', 4).on('keyup change', function () {
				$(this).next('.a-slider').slider("value", parseInt($(this).val(), 10));
				$(this).trigger('input');
			}).after('<div class="a-slider"></div>');

			$input.next('.a-slider').slider({
				min: parseInt($input.attr('min'), 10),
				max: parseInt($input.attr('max'), 10),
				step: parseInt($input.attr('step'), 10),
				value: parseInt($input.attr('value'), 10),
				change: function (event, ui) {
					$(this).prev('input').val(ui.value).trigger('input');
				},
				slide: function (event, ui) {
					$(this).prev('input').val(ui.value).trigger('input');
				}
			});
		});

		editor.find("#image").on('image:select', function (event, attachment) {
			imageeditor.get(0).style.backgroundImage = 'url("' + attachment.url + '")';
		});

		editor.find("#image_logo").on('image:select', function (event, attachment) {
			logoeditor.get(0).style.backgroundImage = 'url("' + attachment.url + '")';
		});

		editor.find("#text__ttf_upload").on('file:select', function (event, attachment) {
			$(this).parent().find('.filename').html(attachment.filename);
		});

		editor.find('#text__font').on('keyup blur paste input change', function () {
			editor.get(0).style.setProperty('--text-font', $(this).val());
			editor.attr('data-font', $(this).val());
		}).trigger('blur'); // font face is defined in *admin.php

		editor.find('#text_enabled').on('change', function () {
			$('.area--text').toggleClass('invisible', !$(this).is(':checked'));
			if ($(this).is(':checked')) {
				$('.editable').focus();
			}
		}).trigger('change'); // font face is defined in *admin.php

		$('.input-color', editor).each(function () {
			var $input = $(this).find('input');
			new Picker({
				parent: this,
				popup: 'top',
				color: $input.val(),
				onChange: function (color) {
					$input.val(color.hex).css('background-color', hex_to_rgba(color.hex)).trigger('blur');
				},
				// onDone: function(color){},
				// onOpen: function(color){},
				// onClose: function(color){}
			});
		});

		// yoast?? no events on the input, use polling
		var external_images_maybe_changed = function(){
			setTimeout(function(){
				var url;
				// yoast
				if ($('.area--background-alternate.image-source-yoast').length) {
					url = $('#facebook-url-input-metabox').val();
					if (url) {
						$(".area--background-alternate.image-source-yoast .background").get(0).style.backgroundImage = 'url("' + url + '")';
					} else {
						$(".area--background-alternate.image-source-yoast .background").get(0).style.backgroundImage = '';
					}
				}

				// Rank Math; latest rank math uses thumbnail ?????

				// thumbnail
				if ($('.area--background-alternate.image-source-thumbnail').length) {
					url = $('#set-post-thumbnail img').attr('src') || '';
					if (url) {
						$(".area--background-alternate.image-source-thumbnail .background").get(0).style.backgroundImage = 'url("' + url + '")';
					} else {
						$(".area--background-alternate.image-source-thumbnail .background").get(0).style.backgroundImage = '';
					}
				}

				$('#remove-post-thumbnail').not('.b').addClass('b').on('click touchend', function() { external_images_maybe_changed(); });
			}, 500);
		};
		var bind_file_browser_button = function(){
			console.log('binding');
			setTimeout(function (){
				$('button.media-button-select').on('click touchend', external_images_maybe_changed);
			}, 1500);
		};
		setTimeout(function(){
			$(document).on('click touchend', '#facebook-remove-button-metabox', external_images_maybe_changed);
			$(document).on('click touchend', '#remove-post-thumbnail', external_images_maybe_changed);

			$('#facebook-replace-button-metabox').on('click touchend', bind_file_browser_button);
			$('#set-post-thumbnail').on('click touchend', bind_file_browser_button);
		}, 1500);
	});
})(jQuery, 'branded-social-images-editor');
