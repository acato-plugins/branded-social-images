(function($) {
	$('#carbon_fields_container_og_image').after('<div id="cls-og-preview"></div>');

	var og_preview = { '_preview' : 1 };
	var og_preview_url = function(){
		return cls_og.preview_url + '?' + $.param( og_preview );
	};

	$(document).on('carbonFields.apiLoaded', function(e, api) {
		console.log('a');
		$(document).on('carbonFields.fieldUpdated', function(e, fieldName) {
			console.log('---');
			console.log('Field updated: ' + fieldName);
			console.log('New value:');
			console.log(api.getFieldValue(fieldName));
			console.log('---');
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

			var img = p.find('img');
			if (img.length === 0) {
				p.append('<img/>');
				img = p.find('img');
			}
			img.attr('src', og_preview_url());
		});
	});
})(jQuery);
