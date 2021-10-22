/* https://github.com/protonet/jquery.inview */
!function(a){"function"==typeof define&&define.amd?define(["jquery"],a):"object"==typeof exports?module.exports=a(require("jquery")):a(jQuery)}(function(a){function i(){var b,c,d={height:f.innerHeight,width:f.innerWidth};return d.height||(b=e.compatMode,(b||!a.support.boxModel)&&(c="CSS1Compat"===b?g:e.body,d={height:c.clientHeight,width:c.clientWidth})),d}function j(){return{top:f.pageYOffset||g.scrollTop||e.body.scrollTop,left:f.pageXOffset||g.scrollLeft||e.body.scrollLeft}}function k(){if(b.length){var e=0,f=a.map(b,function(a){var b=a.data.selector,c=a.$element;return b?c.find(b):c});for(c=c||i(),d=d||j();e<b.length;e++)if(a.contains(g,f[e][0])){var h=a(f[e]),k={height:h[0].offsetHeight,width:h[0].offsetWidth},l=h.offset(),m=h.data("inview");if(!d||!c)return;l.top+k.height>d.top&&l.top<d.top+c.height&&l.left+k.width>d.left&&l.left<d.left+c.width?m||h.data("inview",!0).trigger("inview",[!0]):m&&h.data("inview",!1).trigger("inview",[!1])}}}var c,d,h,b=[],e=document,f=window,g=e.documentElement;a.event.special.inview={add:function(c){b.push({data:c,$element:a(this),element:this}),!h&&b.length&&(h=setInterval(k,250))},remove:function(a){for(var c=0;c<b.length;c++){var d=b[c];if(d.element===this&&d.data.guid===a.guid){b.splice(c,1);break}}b.length||(clearInterval(h),h=null)}},a(f).on("scroll resize scrollstop",function(){c=d=null}),!g.addEventListener&&g.attachEvent&&g.attachEvent("onfocusin",function(){d=null})});

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
	var logoeditor = editor.find('.area--logo:not(.logo-alternate) .logo');

	;(function ($) {
		$.fn.attachMediaUpload = function () {
			var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

			// Restore the main ID when the add media button is pressed
			jQuery('a.add_media').on('click', function () {
				// console.log('is this really needed?');
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
						// console.log('new file_frame');
						wp.media.model.settings.post.id = current_image_id;
						// Create the media frame.
						file_frame = wp.media.frames.file_frame = wp.media({
							title: 'Select an image or upload one.',
							button: {
								text: 'Use this image',
							},
							library: {
								type: wrap.data('types').split(',')
							},
							multiple: false // Set to true to allow multiple files to be selected
						});

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
					// console.log('opening file_frame');
					file_frame.open();
				});
			});
		};
		$.fn.attachFileUpload = function () {
			var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

			// Restore the main ID when the add media button is pressed
			jQuery('a.add_media').on('click', function () {
				// console.log('is this really needed?');
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
						// console.log('new file_frame');
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
						// console.log(file_frame);
						// When an image is selected, run a callback.
						file_frame.on('select', function () {
							// We set multiple to false so only get one image from the uploader
							attachment = file_frame.state().get('selection').first().toJSON();
							// Do something with attachment.id and/or attachment.url here
							input.trigger('file:select', [attachment]);
							// console.log(attachment);
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
					// console.log('opening file_frame');
					file_frame.open();
				});
			});
		};
	})(jQuery);

	$(document).ready(function () {
		// $('.editable[contenteditable]').keydown(function (e) {
		// 	// trap the return key being pressed
		// 	if (e.keyCode === 13) {
		// 		// insert 2 br tags (if only one br tag is inserted the cursor won't go to the next line)
		// 		document.execCommand('insertHTML', false, '\n');
		// 		// prevent the default behaviour of return key pressed
		// 		return false;
		// 	}
		// });

		var texteditor = editor.find('.editable');
		var texteditor_target = editor.find('textarea.editable-target');

		var decodeEntities = (function() {
			// this prevents any overhead from creating the object each time
			var element = document.createElement('div');

			function decodeHTMLEntities (str) {
				if(str && typeof str === 'string') {
					// strip script/html tags
					str = str.replace(/<script[^>]*>([\S\s]*?)<\/script>/gmi, '');
					str = str.replace(/<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, '');
					element.innerHTML = str;
					str = element.textContent;
					element.textContent = '';
				}

				return str;
			}

			return decodeHTMLEntities;
		})();

		editor.find('h2 .toggle').on('click touchend', function() {
			$(this).closest('[class^="area"]').toggleClass('closed');
		});

		// bugfix: compose.js is preventing things like undo.
		editor.on('keypress keyup keydown', function (e) {
			e.stopPropagation();
		});

		// native JS solution for paste-fix
		texteditor.get(0).addEventListener('paste', function(e) {
			// console.log('PASTE with visual editor');
			setTimeout(function() {
				// strip all HTML
				var text = texteditor.text();
				var html = texteditor.html();
				if (text !== html) {
					text = text.replace('<br[^>]*>/g', '\n');
					// console.log('cleaning from', html);
					// console.log('cleaning to', text);
					texteditor.text(text).trigger('keyup');
				}
			}, 250);
		});

		// update target when editor edited
		texteditor.on('blur keyup paste', function (e) {
			// console.log('interaction with visual editor: ', e);
			setTimeout(function() {
				texteditor_target.val(texteditor.text());
			}, 250);
		});

		texteditor_target.on('blur keyup input', function (e) {
			// console.log('interaction with hidden field: ', e);
			texteditor_target.val(texteditor_target.val().replace(/&nbsp;<br/g, '<br'));
			texteditor_target.val(texteditor_target.val().replace(/&nbsp;\n/g, '\n'));
			texteditor.text(texteditor_target.val());
		});

		// update editor when target edited
		texteditor_target.on('blur keyup paste', function (e) {
			// console.log('copy hidden field to visual: ', e);
			texteditor.text(decodeEntities(texteditor_target.val()));
		}).trigger('paste');

		// text color
		editor.find('#color').on('keyup blur paste input', function () {
			editor.get(0).style.setProperty('--text-color', hex_to_rgba($(this).val()));
		});
		// text background color
		editor.find('#background_enabled,#background_color').on('keyup blur paste input change', function () {
			var use_background = editor.find('#background_enabled').is(':checked');
			editor.toggleClass('with-text-background', use_background);
			editor.get(0).style.setProperty('--text-background', hex_to_rgba(editor.find('#background_color').val()));
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
		editor.find('#disabled').on('change', function(){
			editor.toggleClass('bsi-disabled', $(this).is(':checked'));
		});

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
			var logo_min = parseInt( $(this).attr('min'), 10 );
			var logo_max = parseInt( $(this).attr('max'), 10 );
			if (v < logo_min) {
				v = logo_min;
			}
			if (v > logo_max) {
				v = logo_max;
			}
			editor.get(0).style.setProperty('--logo-scale', v);

		}).trigger('blur');

		// font size
		$('#text__font_size').on('keyup blur paste input', function () {
			var v = parseInt("0" + $(this).val(), 10);
			var fs_min = parseInt( $(this).attr('min'), 10 );
			var fs_max = parseInt( $(this).attr('max'), 10 );
			if (v < fs_min) {
				v = fs_min;
			}
			if (v > fs_max) {
				v = fs_max;
			}
			editor.get(0).style.setProperty('--font-size', v + "px");
			editor.get(0).style.setProperty('--line-height', (v*1.25) + "px");

		}).trigger('blur');

		// sliders
		$('.add-slider').each(function () {
			var $input = $(this).find('input');
			$input.attr('size', 4).on('blur change', function () {
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
			if ('id' in attachment && parseInt(""+attachment.id, 10) > 0) {
				editor.get(0).style.setProperty('--logo-width', attachment.width);
				editor.get(0).style.setProperty('--logo-height', attachment.height);
				logoeditor.get(0).style.backgroundImage = 'url("' + attachment.url + '")';
				editor.addClass('with-logo');
			}
			else {
				editor.get(0).style.setProperty('--logo-width', 410); // this is the example logo
				editor.get(0).style.setProperty('--logo-height', 82);
				logoeditor.get(0).style.backgroundImage = '';
				editor.removeClass('with-logo');
			}
		});

		editor.find("#text__ttf_upload").on('file:select', function (event, attachment) {
			$(this).parent().find('.filename').html(attachment.filename);
		});

		editor.find("i.toggle-comment,i.toggle-info").on('click touchend', function(){
			$(this).toggleClass('active');
		});

		editor.find('#text__font').on('keyup blur paste input change', function () {
			editor.get(0).style.setProperty('--text-font', $(this).val());
			editor.attr('data-font', $(this).val());
		}).trigger('blur'); // font face is defined in *admin.php

		// editor.on('inview', function(){
		// 	$('.editable').focus();
		// });

		editor.find('#text_enabled').on('change', function () {
			$('.area--text').toggleClass('invisible', !$(this).is(':checked'));
		}).trigger('change'); // font face is defined in *admin.php

		$('.input-color', editor).each(function () {
			var $input = $(this).find('input');
			new Picker({
				parent: this,
				popup: 'top',
				color: $input.val(),
				onChange: function (color) {
					$input.val(color.hex.toUpperCase()).parent().get(0).style.setProperty('--the-color', hex_to_rgba(color.hex));
					$input.trigger('blur');
				},
				// onDone: function(color){},
				// onOpen: function(color){},
				// onClose: function(color){}
			});
		});

		var getFeaturedImage = function(){};
		// window.getFeaturedImage = getFeaturedImage;
		var subscribe;

		if (jQuery('body').is('.block-editor-page')) {

			var select = wp.data.select;
			subscribe = wp.data.subscribe;

			var _coreDataSelect, _coreEditorSelect;

			var getMediaById = function(mediaId) {
				if (!_coreDataSelect) {
					_coreDataSelect = select("core");
				}

				return _coreDataSelect.getMedia(mediaId);
			};

			var getPostAttribute = function(attribute) {
				if (!_coreEditorSelect) {
					_coreEditorSelect = select("core/editor");
				}

				return _coreEditorSelect.getEditedPostAttribute(attribute);
			};

			getFeaturedImage = function () {
				const featuredImage = getPostAttribute("featured_media");
				if (featuredImage) {
					const mediaObj = getMediaById(featuredImage);

					if (mediaObj) {
						if (bsi_settings.image_size_name in mediaObj.media_details.sizes) {
							return mediaObj.media_details.sizes[bsi_settings.image_size_name].source_url;
						}
						return mediaObj.source_url;
					}
				}

				return null;
			};
		}
		else {
			getFeaturedImage = function () {
				return $('#set-post-thumbnail img').attr('src') || '';
			};
		}

		// yoast?? no events on the input, use polling
		var getYoastFacebookImage = function(){
			var $field = $('#facebook-url-input-metabox');
			var $preview = $('#wpseo-section-social > div:nth(0) .yoast-image-select__preview--image');
			if (!$field.length && !$preview.length) {
				return false;
			}
			url = $field.val();
			if (!url) {
				url = $preview.attr('src');
			}

			return url;
		};
		var state = { yoast: getYoastFacebookImage(), featured: getFeaturedImage() };

		window.i_state = state;
		window.i_gyfi = getYoastFacebookImage;
		window.i_gfi = getFeaturedImage;

		var external_images_maybe_changed = function(){
			setTimeout(function(){
				var url;
				// yoast
				if ($('.area--background-alternate.image-source-yoast').length) {
					url = getYoastFacebookImage();
					if (state.yoast !== url) {
						state.yoast = url;
						if (url) {
							$(".area--background-alternate.image-source-yoast .background").get(0).style.backgroundImage = 'url("' + url + '")';
						} else {
							$(".area--background-alternate.image-source-yoast .background").get(0).style.backgroundImage = '';
						}
					}
				}

				// thumbnail
				if ($('.area--background-alternate.image-source-thumbnail').length) {
					url = getFeaturedImage();
					if (state.featured !== url) {
						state.featured = url;
						if (url) {
							$(".area--background-alternate.image-source-thumbnail .background").get(0).style.backgroundImage = 'url("' + url + '")';
						} else {
							$(".area--background-alternate.image-source-thumbnail .background").get(0).style.backgroundImage = '';
						}
					}
				}
			}, 500);
		};

		if (jQuery('body').is('.block-editor-page')) {
			var debounce;
			subscribe(function() {
				if (debounce) { clearTimeout(debounce); }
				debounce = setTimeout(function() { external_images_maybe_changed(); }, 1000);
			});
		}
		setInterval(external_images_maybe_changed, 5000);
	});
})(jQuery, 'branded-social-images-editor');
