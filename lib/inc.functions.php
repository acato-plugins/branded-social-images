<?php
/**
 * A collection of functions that are used throughout the plugin.
 *
 * @package Acato\Plugins\OGImage
 * @since   1.0.15
 */

/**
 * Some hosts do not have the mime_content_type function.
 * In case it is missing, try alternatives
 *
 * @since   1.0.15
 */
foreach (
	[
		'mime_content_type',
		'finfo_open',
		'wp_check_filetype',
		'exec',
		'shell_exec',
		'passthru',
		'system',
	] as $bsi_function_name
) {
	// Define a constant to indicate if the function existed before the patch.
	// We use this to determine if we actually needed to implement the function ourselves.
	// This will be listed in the debug information.
	define( 'BSI_' . strtoupper( $bsi_function_name . '_EXISTED_BEFORE_PATCH' ), function_exists( $bsi_function_name ) );
}

if ( ! function_exists( 'mime_content_type' ) ) {
	/**
	 * Get the mime type of a file.
	 *
	 * @param string $file The file to get the mime type of.
	 *
	 * @return false|mixed|string
	 */
	function mime_content_type( $file ) {
		/**
		 * Alternative 1: finfo
		 * Open a connection to the finfo service and ask for the content-type of the file.
		 */
		static $f;
		if ( function_exists( 'finfo_open' ) ) {
			if ( ! $f ) {
				$f = finfo_open( FILEINFO_MIME_TYPE );
			}
			if ( $f ) {
				return finfo_file( $f, $file );
			}
		}

		/**
		 * Alternative 2: the WordPress function wp_check_filetype
		 * Why is this not used always, or as an earlier alternative?
		 * Because it falls back on detection by extension, which is far less preferable than mime sniffing
		 * And because it might not exist when we need it. (many parts of WordPress are not available during bootstrap or on front-end)
		 */
		if ( function_exists( 'wp_check_filetype' ) ) {
			$d = wp_check_filetype( $file, [] );
			if ( $d ) {
				return $d['type'];
			}
		}

		/**
		 * If all else fails;
		 * Alternative 3: mime type based on file extension.
		 * Now this is a potential security risk, but if you are that keen on this; please just fix your webserver to properly
		 * support mime-sniffing on files.
		 *
		 * For our purposes, we only need image/jpeg and image/png, but since this function is missing and we are not alone
		 * in this universe, we need to provide a fairly complete solution.
		 */
		$mime_types = [
			'txt'  => 'text/plain',
			'htm'  => 'text/html',
			'html' => 'text/html',
			'php'  => 'text/html',
			'css'  => 'text/css',
			'js'   => 'application/javascript',
			'json' => 'application/json',
			'xml'  => 'application/xml',
			'swf'  => 'application/x-shockwave-flash',
			'flv'  => 'video/x-flv',
			// images.
			'png'  => 'image/png',
			'jpe'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg'  => 'image/jpeg',
			'gif'  => 'image/gif',
			'bmp'  => 'image/bmp',
			'ico'  => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif'  => 'image/tiff',
			'svg'  => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			// archives.
			'zip'  => 'application/zip',
			'rar'  => 'application/x-rar-compressed',
			'exe'  => 'application/x-msdownload',
			'msi'  => 'application/x-msdownload',
			'cab'  => 'application/vnd.ms-cab-compressed',
			// audio/video.
			'mp3'  => 'audio/mpeg',
			'qt'   => 'video/quicktime',
			'mov'  => 'video/quicktime',
			// adobe.
			'pdf'  => 'application/pdf',
			'psd'  => 'image/vnd.adobe.photoshop',
			'ai'   => 'application/postscript',
			'eps'  => 'application/postscript',
			'ps'   => 'application/postscript',
			// ms office.
			'doc'  => 'application/msword',
			'rtf'  => 'application/rtf',
			'xls'  => 'application/vnd.ms-excel',
			'ppt'  => 'application/vnd.ms-powerpoint',
			// open office.
			'odt'  => 'application/vnd.oasis.opendocument.text',
			'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
		];

		$file = explode( '.', $file );
		$ext  = strtolower( array_pop( $file ) );
		if ( array_key_exists( $ext, $mime_types ) ) {
			return $mime_types[ $ext ];
		}

		return 'application/octet-stream';
	}
}
