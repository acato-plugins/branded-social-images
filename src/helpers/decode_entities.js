export default (function () {
	// this prevents any overhead from creating the object each time
	var element = document.createElement('div');

	function decodeHTMLEntities(str) {
		if (str && typeof str === 'string') {
			// Fill HTML into element.
			element.innerHTML = str;
			// Find all script tags and remove them.
			element.querySelectorAll('script').forEach( n => n.remove() );
			element.querySelectorAll('iframe').forEach( n => n.remove() );
			// Convert all other tags to their plain-text content.
			element.querySelectorAll('*').forEach( n => n.textContent );
			// Get the plain text.
			str = element.textContent;
			element.textContent = '';
		}

		return str;
	}

	return decodeHTMLEntities;
})();
