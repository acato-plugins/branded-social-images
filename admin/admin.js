(function($) {
	$('#carbon_fields_container_og_image').after('<div id="cls-og-preview"></div>');
	var og_preview = { '_preview' : 1 };
	var og_preview_url = function(){
		return cls_og.preview_url + '?' + $.param( og_preview );
	};

	var update_delay = false;

	$(document).on('carbonFields.apiLoaded', function(e, api) {
		$(document).on('carbonFields.fieldUpdated', function(e, fieldName) {
			if (update_delay) {
				clearTimeout(update_delay);
			}
			update_delay = setTimeout(function(){
				var v = api.getFieldValue(fieldName), p = $("#cls-og-preview");
				if (fieldName === 'cls_og_image') {
					og_preview.image = v; // this is an ID
				}
				if (fieldName === 'cls_og_text') {
					og_preview.text = encodeURIComponent(v);
				}
				if (fieldName === 'cls_og_text_position') {
					og_preview.text_position = encodeURIComponent(v);
				}
				if (fieldName === 'cls_og_text_enabled') {
					og_preview.text_enabled = v ? 'yes' : 'no';

					$("[name='_cls_og_text'],[name='_cls_og_text_position']").each(function(){
						console.log($(this).closest('div.carbon-field').toggle(v));
					});
				}
				if (fieldName === 'cls_og_logo_position') {
					og_preview.logo_position = encodeURIComponent(v);
				}
				if (fieldName === 'cls_og_logo_enabled') {
					og_preview.logo_enabled = v ? 'yes' : 'no';

					$("[name='_cls_og_logo_position']").each(function(){
						console.log($(this).closest('div.carbon-field').toggle(v));
					});
				}

				var img = p.find('img');
				if (img.length === 0) {
					p.append('<img/>');
					img = p.find('img');
				}
				img.attr('src', og_preview_url());
			}, 1000);
		}).trigger('carbonFields.fieldUpdated', [ 'cls_og_logo_enabled' ]);
	});
})(jQuery);
