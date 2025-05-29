<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing -- there clearly is a comment .
/**
 * This is a test: can we execute tools from PHP?.
 * This file will call the webp conversion tool.
 *
 * @package    Acato/Plugins/BrandedSocialImages
 */
function bsi_execute_system_call_test() {
	echo 'Testing ability to call the conversion tools from PHP; ' . PHP_EOL;

	$d = __DIR__;

	// @phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
	$result = exec( '"' . $d . '/dwebp" "' . $d . '/test.webp" -o "' . $d . '/test.png"' );

	$succes = is_file( $d . '/test.png' );
	if ( $succes ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- cannot use wp_delete_file as we are not in WP context.
		unlink( $d . '/test.png' );
	}
	echo $succes ? 'It works!' : 'Sorry, it did not work';
	echo PHP_EOL;

	// @phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- we are not in WP context.
	touch( $d . '/can-execute-binaries-from-php.' . ( $succes ? 'success' : 'fail' ) );
}

bsi_execute_system_call_test();
