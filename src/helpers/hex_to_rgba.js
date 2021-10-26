export default function hex_to_rgba(hex) {
	var c;
	// #ABC or #ABCD
	if (/^#([A-Fa-f0-9]{3,4})$/.test(hex)) {
		c = (hex + 'F').substring(1).split('');
		return hex_to_rgba(c[0], c[0], c[1], c[1], c[2], c[2], c[3], c[3]);
	}

	c = '0x' + (hex.substring(1) + 'FF').substring(0, 8);
	return 'rgba(' + [(c >> 24) & 255, (c >> 16) & 255, (c >> 8) & 255, Math.round((c & 255) / 25.5) / 10].join(',') + ')';
}
